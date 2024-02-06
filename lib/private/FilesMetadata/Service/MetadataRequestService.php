<?php

declare(strict_types=1);
/**
 * @copyright 2023 Maxence Lange <maxence@artificial-owl.com>
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\FilesMetadata\Service;

use OC\FilesMetadata\Model\FilesMetadata;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\FilesMetadata\Exceptions\FilesMetadataNotFoundException;
use OCP\FilesMetadata\Model\IFilesMetadata;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

/**
 * manage sql request to the metadata table
 */
class MetadataRequestService {
	public const TABLE_METADATA = 'files_metadata';

	public function __construct(
		private IDBConnection $dbConnection,
		private LoggerInterface $logger
	) {
	}

	/**
	 * store metadata into database
	 *
	 * @param IFilesMetadata $filesMetadata
	 *
	 * @throws Exception
	 */
	public function store(IFilesMetadata $filesMetadata): void {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->insert(self::TABLE_METADATA)
		   ->setValue('file_id', $qb->createNamedParameter($filesMetadata->getFileId(), IQueryBuilder::PARAM_INT))
		   ->setValue('json', $qb->createNamedParameter(json_encode($filesMetadata->jsonSerialize())))
		   ->setValue('sync_token', $qb->createNamedParameter($this->generateSyncToken()))
		   ->setValue('last_update', (string) $qb->createFunction('NOW()'));
		$qb->executeStatement();
	}

	/**
	 * returns metadata for a file id
	 *
	 * @param int $fileId file id
	 *
	 * @return IFilesMetadata
	 * @throws FilesMetadataNotFoundException if no metadata are found in database
	 */
	public function getMetadataFromFileId(int $fileId, string $etag = ''): IFilesMetadata {
		try {
			$qb = $this->dbConnection->getQueryBuilder();
			$qb->select('m.json', 'm.sync_token')->from(self::TABLE_METADATA, 'm');
			if ($etag === '') {
				/**
				 * in the rare case $etag is not known yet, we left-join filecache to
				 * get the current etag linked to the file id.
				 */
				$qb->addSelect('f.etag')
				   ->leftJoin('m', 'filecache', 'f', $qb->expr()->eq('m.file_id', 'f.fileid'));
			}
			$qb->where($qb->expr()->eq('m.file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
			$result = $qb->executeQuery();
			$data = $result->fetch();
			$result->closeCursor();
		} catch (Exception $e) {
			$this->logger->warning('exception while getMetadataFromDatabase()', ['exception' => $e, 'fileId' => $fileId]);
			throw new FilesMetadataNotFoundException();
		}

		if ($data === false) {
			throw new FilesMetadataNotFoundException();
		}

		// we can assume $data['etag'] is null only if $etag is an empty string.
		$metadata = new FilesMetadata($fileId, $data['etag'] ?? $etag);
		$metadata->importFromDatabase($data);

		return $metadata;
	}


	/**
	 * returns metadata for multiple file ids
	 *
	 * @param array $fileIds file ids
	 *
	 * @return array File ID is the array key, files without metadata are not returned in the array
	 * @psalm-return array<int, IFilesMetadata>
	 */
	public function getMetadataFromFileIds(array $fileIds): array {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('m.file_id', 'm.json', 'm.sync_token')->from(self::TABLE_METADATA);
		$qb->addSelect('f.etag')
		   ->leftJoin('m', 'filecache', 'f', $qb->expr()->eq('m.file_id', 'f.fileid'));
		$qb->where($qb->expr()->in('m.file_id', $qb->createNamedParameter($fileIds, IQueryBuilder::PARAM_INT_ARRAY)));

		$list = [];
		$result = $qb->executeQuery();
		while ($data = $result->fetch()) {
			$fileId = (int) $data['file_id'];
			$metadata = new FilesMetadata($fileId, $data['etag'] ?? '');
			try {
				$metadata->importFromDatabase($data);
			} catch (FilesMetadataNotFoundException) {
				continue;
			}
			$list[$fileId] = $metadata;
		}
		$result->closeCursor();

		return $list;
	}

	/**
	 * drop metadata related to a file id
	 *
	 * @param int $fileId file id
	 *
	 * @return void
	 * @throws Exception
	 */
	public function dropMetadata(int $fileId): void {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->delete(self::TABLE_METADATA)
		   ->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * update metadata in the database
	 *
	 * @param IFilesMetadata $filesMetadata metadata
	 *
	 * @return int number of affected rows
	 * @throws Exception
	 */
	public function updateMetadata(IFilesMetadata $filesMetadata): int {
		$qb = $this->dbConnection->getQueryBuilder();
		$expr = $qb->expr();

		$qb->update(self::TABLE_METADATA)
		   ->set('json', $qb->createNamedParameter(json_encode($filesMetadata->jsonSerialize())))
		   ->set('sync_token', $qb->createNamedParameter($this->generateSyncToken()))
		   ->set('last_update', $qb->createFunction('NOW()'))
		   ->where(
		   	$expr->andX(
		   		$expr->eq('file_id', $qb->createNamedParameter($filesMetadata->getFileId(), IQueryBuilder::PARAM_INT)),
		   		$expr->eq('sync_token', $qb->createNamedParameter($filesMetadata->getSyncToken()))
		   	)
		   );

		return $qb->executeStatement();
	}

	/**
	 * generate a random token
	 * @return string
	 */
	private function generateSyncToken(): string {
		$chars = 'qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM1234567890';

		$str = '';
		$max = strlen($chars);
		for ($i = 0; $i < 7; $i++) {
			try {
				$str .= $chars[random_int(0, $max - 2)];
			} catch (\Exception $e) {
				$this->logger->warning('exception during generateSyncToken', ['exception' => $e]);
			}
		}

		return $str;
	}
}
