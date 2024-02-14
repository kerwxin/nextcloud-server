<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2024 Robin Appelman <robin@icewind.nl>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\Files\Cache;

use OC\DB\Exceptions\DbalException;
use OC\SystemConfig;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\FilesMetadata\IFilesMetadataManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class Database {
	private ICache $cache;

	public function __construct(
		private IDBConnection $connection, // todo: multiple db connections for sharding (open connection lazy?)
		private SystemConfig $systemConfig,
		private LoggerInterface $logger,
		private IFilesMetadataManager $filesMetadataManager,
		ICacheFactory $cacheFactory,
	) {
		$this->cache = $cacheFactory->createLocal('storage_by_fileid');
	}

	private function connectionForStorageId(int $storage): IDBConnection {
		return $this->databaseForShard($this->getShardForStorageId($storage));
	}

	public function queryForStorageId(int $storage): CacheQueryBuilder {
		return $this->queryForShard($this->getShardForStorageId($storage));
	}

	private function databaseForShard(int $shard): IDBConnection {
		return $this->connection;
	}

	private function queryForShard(int $shard): CacheQueryBuilder {
		// todo: select db based on shard
		$query = new CacheQueryBuilder(
			$this->databaseForShard($shard),
			$this->systemConfig,
			$this->logger,
			$this->filesMetadataManager
		);
		$query->allowTable('filecache');
		$query->allowTable('filecache_extended');
		$query->allowTable('files_metadata');
		return $query;
	}

	public function getByFileId(int $fileId): ?CacheEntry {
		$cachedStorage = $this->getCachedStorageIdForFileId($fileId);
		if ($cachedStorage) {
			$result = $this->queryByFileIdInShard($fileId, $this->getShardForStorageId($cachedStorage));
			if ($result && $result->getId() === $fileId) {
				return $result;
			}
		}

		foreach ($this->getAllShards() as $shard) {
			$result = $this->queryByFileIdInShard($fileId, $shard);
			if ($result) {
				$this->cache->set((string)$fileId, $result->getStorageId());
				return $result;
			}
		}
		return null;
	}

	private function queryByFileIdInShard(int $fileId, int $shard): ?CacheEntry {
		$query = $this->queryForShard($shard)->selectFileCache();
		$query->andWhere($query->expr()->eq('fileid', $fileId, IQueryBuilder::PARAM_INT));

		$row = $query->executeQuery()->fetchOne();
		return $row ? new CacheEntry($row) : null;
	}

	/**
	 * @param list<int> $fileIds
	 * @return array<int, CacheEntry>
	 */
	public function getByFileIds(array $fileIds): array {
		$cachedStorages = $this->getCachedShardsForFileIds($fileIds);

		$foundItems = [];
		foreach ($cachedStorages as $shard => $fileIdsForShard) {
			$foundItems += $this->queryByFileIdsInShard($shard, $fileIdsForShard);
		}

		$remainingIds = array_diff($fileIds, array_keys($foundItems));

		if ($remainingIds) {
			foreach ($this->getAllShards() as $shard) {
				$items = $this->queryByFileIdsInShard($shard, $remainingIds);
				$remainingIds = array_diff($remainingIds, array_keys($items));
				$foundItems += $items;

				if (count($remainingIds) === 0) {
					break;
				}
			}
		}
		return array_values($foundItems);
	}

	/**
	 * @param list<int> $fileIds
	 * @return array<int, CacheEntry>
	 */
	public function queryByFileIdsInShard(int $shard, array $fileIds): array {
		$query = $this->queryForShard($shard)->selectFileCache();
		$query->andWhere($query->expr()->eq('fileid', $fileIds, IQueryBuilder::PARAM_INT));
		$result = $query->executeQuery();
		$items = [];
		while ($row = $result->fetchOne()) {
			$items[(int)$row['fileid']] = new CacheEntry($row);
		}
		return $items;
	}

	private function getCachedStorageIdForFileId(int $fileId): ?int {
		$cached = $this->cache->get((string)$fileId);
		return ($cached === null) ? null : (int)$cached;
	}

	/**
	 * @param list<int> $fileIds
	 * @return array<int, list<int>>
	 */
	private function getCachedShardsForFileIds(array $fileIds): array {
		$result = [];
		foreach ($fileIds as $fileId) {
			$storageId = $this->getCachedStorageIdForFileId($fileId);
			if ($storageId) {
				$shard = $this->getShardForStorageId($storageId);
				$result[$shard][] = $fileId;
			}
		}
		return $result;
	}

	private function getShardForStorageId(int $storage): int {
		return 0;
	}

	/**
	 * @return list<int>
	 */
	private function getAllShards(): array {
		return [0];
	}

	public function beginTransaction(int $storageId): void {
		$this->connectionForStorageId($storageId)->beginTransaction();
	}

	public function inTransaction(int $storageId): bool {
		return $this->connectionForStorageId($storageId)->inTransaction();
	}

	public function commit(int $storageId): void {
		$this->connectionForStorageId($storageId)->commit();
	}

	public function rollBack(int $storageId): void {
		$this->connectionForStorageId($storageId)->rollBack();
	}
}
