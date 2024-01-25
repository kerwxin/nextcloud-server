<?php
/**
 * Copyright (c) 2013 Christopher Schäpers <christopher@schaepers.it>
 * Copyright (c) 2013 Bart Visscher <bartv@thisnet.nl>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test;

use OC\AppConfig;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

/**
 * Class AppConfigTest
 *
 * @group DB
 *
 * @package Test
 */
class AppConfigTest extends TestCase {
	/** @var \OCP\IAppConfig */
	protected $appConfig;

	protected IDBConnection $connection;
	private LoggerInterface $logger;
	private ICrypto $crypto;

	protected $originalConfig;

	protected function setUp(): void {
		parent::setUp();

		$this->connection = \OCP\Server::get(IDBConnection::class);
		$this->logger = \OCP\Server::get(LoggerInterface::class);
		$this->crypto = \OCP\Server::get(ICrypto::class);

		$sql = $this->connection->getQueryBuilder();
		$sql->select('*')
			->from('appconfig');
		$result = $sql->executeQuery();
		$this->originalConfig = $result->fetchAll();
		$result->closeCursor();

		$sql = $this->connection->getQueryBuilder();
		$sql->delete('appconfig');
		$sql->executeStatement();

		$sql = $this->connection->getQueryBuilder();
		$sql->insert('appconfig')
			->values([
				'appid' => $sql->createParameter('appid'),
				'configkey' => $sql->createParameter('configkey'),
				'configvalue' => $sql->createParameter('configvalue')
			]);

		$sql->setParameters([
			'appid' => 'testapp',
			'configkey' => 'enabled',
			'configvalue' => 'true'
		])->executeStatement();
		$sql->setParameters([
			'appid' => 'testapp',
			'configkey' => 'installed_version',
			'configvalue' => '1.2.3',
		])->executeStatement();
		$sql->setParameters([
			'appid' => 'testapp',
			'configkey' => 'depends_on',
			'configvalue' => 'someapp',
		])->executeStatement();
		$sql->setParameters([
			'appid' => 'testapp',
			'configkey' => 'deletethis',
			'configvalue' => 'deletethis',
		])->executeStatement();
		$sql->setParameters([
			'appid' => 'testapp',
			'configkey' => 'key',
			'configvalue' => 'value',
		])->executeStatement();

		$sql->setParameters([
			'appid' => 'someapp',
			'configkey' => 'key',
			'configvalue' => 'value',
		])->executeStatement();
		$sql->setParameters([
			'appid' => 'someapp',
			'configkey' => 'otherkey',
			'configvalue' => 'othervalue',
		])->executeStatement();

		$sql->setParameters([
			'appid' => '123456',
			'configkey' => 'key',
			'configvalue' => 'value',
		])->executeStatement();
		$sql->setParameters([
			'appid' => '123456',
			'configkey' => 'enabled',
			'configvalue' => 'false',
		])->executeStatement();

		$sql->setParameters([
			'appid' => 'anotherapp',
			'configkey' => 'key',
			'configvalue' => 'value',
		])->executeStatement();
		$sql->setParameters([
			'appid' => 'anotherapp',
			'configkey' => 'enabled',
			'configvalue' => 'false',
		])->executeStatement();
	}

	protected function tearDown(): void {
		$sql = $this->connection->getQueryBuilder();
		$sql->delete('appconfig');
		$sql->executeStatement();

		$sql = $this->connection->getQueryBuilder();
		$sql->insert('appconfig')
			->values([
				'appid' => $sql->createParameter('appid'),
				'configkey' => $sql->createParameter('configkey'),
				'configvalue' => $sql->createParameter('configvalue'),
				'lazy' => $sql->createParameter('lazy'),
				'type' => $sql->createParameter('type'),
			]);

		foreach ($this->originalConfig as $configs) {
			$sql->setParameter('appid', $configs['appid'])
				->setParameter('configkey', $configs['configkey'])
				->setParameter('configvalue', $configs['configvalue'])
				->setParameter('lazy', ($configs['lazy'] === '1') ? '1' : '0')
				->setParameter('type', $configs['type']);
			$sql->executeStatement();
		}

		$this->restoreService(AppConfig::class);
		parent::tearDown();
	}

	protected function createAppConfig(): AppConfig {
		return new AppConfig(
			$this->connection,
			$this->logger,
			$this->crypto,
		);
	}

	public function testGetApps(): void {
		$config = $this->createAppConfig();

		$this->assertEqualsCanonicalizing([
			'anotherapp',
			'someapp',
			'testapp',
			123456,
		], $config->getApps());
	}

	public function testGetKeys(): void {
		$config = $this->createAppConfig();

		$keys = $config->getKeys('testapp');
		$this->assertEqualsCanonicalizing([
			'deletethis',
			'depends_on',
			'enabled',
			'installed_version',
			'key',
		], $keys);
	}

	public function testGetValue(): void {
		$config = $this->createAppConfig();

		$value = $config->getValue('testapp', 'installed_version');
		$this->assertConfigKey('testapp', 'installed_version', $value);

		$value = $config->getValue('testapp', 'nonexistant');
		$this->assertNull($value);

		$value = $config->getValue('testapp', 'nonexistant', 'default');
		$this->assertEquals('default', $value);
	}

	public function testHasKey(): void {
		$config = $this->createAppConfig();

		$this->assertTrue($config->hasKey('testapp', 'installed_version'));
		$this->assertFalse($config->hasKey('testapp', 'nonexistant'));
		$this->assertFalse($config->hasKey('nonexistant', 'nonexistant'));
	}

	public function testSetValueUpdate(): void {
		$config = $this->createAppConfig();

		$this->assertEquals('1.2.3', $config->getValue('testapp', 'installed_version'));
		$this->assertConfigKey('testapp', 'installed_version', '1.2.3');

		$wasModified = $config->setValue('testapp', 'installed_version', '1.2.3');
		if (!$this->connection instanceof \OC\DB\OracleConnection) {
			$this->assertFalse($wasModified);
		}

		$this->assertEquals('1.2.3', $config->getValue('testapp', 'installed_version'));
		$this->assertConfigKey('testapp', 'installed_version', '1.2.3');

		$this->assertTrue($config->setValue('testapp', 'installed_version', '1.33.7'));


		$this->assertEquals('1.33.7', $config->getValue('testapp', 'installed_version'));
		$this->assertConfigKey('testapp', 'installed_version', '1.33.7');

		$config->setValue('someapp', 'somekey', 'somevalue');
		$this->assertConfigKey('someapp', 'somekey', 'somevalue');
	}

	public function testSetValueInsert(): void {
		$config = $this->createAppConfig();

		$this->assertFalse($config->hasKey('someapp', 'somekey'));
		$this->assertNull($config->getValue('someapp', 'somekey'));

		$this->assertTrue($config->setValue('someapp', 'somekey', 'somevalue'));

		$this->assertTrue($config->hasKey('someapp', 'somekey'));
		$this->assertEquals('somevalue', $config->getValue('someapp', 'somekey'));
		$this->assertConfigKey('someapp', 'somekey', 'somevalue');

		$wasInserted = $config->setValue('someapp', 'somekey', 'somevalue');
		if (!$this->connection instanceof \OC\DB\OracleConnection) {
			$this->assertFalse($wasInserted);
		}
	}

	public function testDeleteKey(): void {
		$config = $this->createAppConfig();

		$this->assertTrue($config->hasKey('testapp', 'deletethis'));

		$config->deleteKey('testapp', 'deletethis');

		$this->assertFalse($config->hasKey('testapp', 'deletethis'));

		$this->assertFalse($this->loadConfigValueFromDatabase('testapp', 'deletethis'));
	}

	public function testDeleteApp(): void {
		$config = $this->createAppConfig();

		$this->assertTrue($config->hasKey('someapp', 'otherkey'));

		$config->deleteApp('someapp');

		$this->assertFalse($config->hasKey('someapp', 'otherkey'));

		$sql = $this->connection->getQueryBuilder();
		$sql->select('configvalue')
			->from('appconfig')
			->where($sql->expr()->eq('appid', $sql->createParameter('appid')))
			->setParameter('appid', 'someapp');
		$query = $sql->executeQuery();
		$result = $query->fetch();
		$query->closeCursor();
		$this->assertFalse($result);
	}

	public function testGetValuesNotAllowed(): void {
		$config = $this->createAppConfig();

		$this->assertFalse($config->getValues('testapp', 'enabled'));

		$this->assertFalse($config->getValues(false, false));
	}

	public function testGetValues(): void {
		$config = $this->createAppConfig();

		$sql = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$sql->select(['configkey', 'configvalue'])
			->from('appconfig')
			->where($sql->expr()->eq('appid', $sql->createParameter('appid')))
			->setParameter('appid', 'testapp');
		$query = $sql->executeQuery();
		$expected = [];
		while ($row = $query->fetch()) {
			$expected[$row['configkey']] = $row['configvalue'];
		}
		$query->closeCursor();

		$values = $config->getValues('testapp', false);
		$this->assertEquals($expected, $values);

		$sql = $this->connection->getQueryBuilder();
		$sql->select(['appid', 'configvalue'])
			->from('appconfig')
			->where($sql->expr()->eq('configkey', $sql->createParameter('configkey')))
			->setParameter('configkey', 'enabled');
		$query = $sql->executeQuery();
		$expected = [];
		while ($row = $query->fetch()) {
			$expected[$row['appid']] = $row['configvalue'];
		}
		$query->closeCursor();

		$values = $config->getValues(false, 'enabled');
		$this->assertEquals($expected, $values);
	}

	public function testGetFilteredValues(): void {
		$config = $this->createAppConfig();
		$config->setValue('user_ldap', 'ldap_agent_password', 'secret');
		$config->setValue('user_ldap', 's42ldap_agent_password', 'secret');
		$config->setValue('user_ldap', 'ldap_dn', 'dn');

		$values = $config->getFilteredValues('user_ldap');
		$this->assertEquals([
			'ldap_agent_password' => IConfig::SENSITIVE_VALUE,
			's42ldap_agent_password' => IConfig::SENSITIVE_VALUE,
			'ldap_dn' => 'dn',
		], $values);
	}

	public function testSettingConfigParallel(): void {
		$appConfig1 = $this->createAppConfig();
		$appConfig2 = $this->createAppConfig();
		$appConfig1->getValue('testapp', 'foo', 'v1');
		$appConfig2->getValue('testapp', 'foo', 'v1');

		$appConfig1->setValue('testapp', 'foo', 'v1');
		$this->assertConfigKey('testapp', 'foo', 'v1');

		$appConfig2->setValue('testapp', 'foo', 'v2');
		$this->assertConfigKey('testapp', 'foo', 'v2');
	}

	public function testSensitiveValuesAreEncrypted(): void {
		$appConfig = $this->createAppConfig();
		$secret = md5(time());
		$appConfig->setValueString('testapp', 'secret', $secret, sensitive: true);

		$this->assertConfigValueNotEquals('testapp', 'secret', $secret);

		// Can get in same run
		$actualSecret = $appConfig->getValueString('testapp', 'secret');
		$this->assertEquals($secret, $actualSecret);

		// Can get freshly decrypted from DB
		$newAppConfig = $this->createAppConfig();
		$actualSecret = $newAppConfig->getValueString('testapp', 'secret');
		$this->assertEquals($secret, $actualSecret);
	}

	public function testMigratingNonSensitiveValueToSensitiveOne(): void {
		$appConfig = $this->createAppConfig();
		$secret = sha1(time());

		// Unencrypted
		$appConfig->setValueString('testapp', 'migrating-secret', $secret);
		$this->assertConfigKey('testapp', 'migrating-secret', $secret);

		// Can get freshly decrypted from DB
		$newAppConfig = $this->createAppConfig();
		$actualSecret = $newAppConfig->getValueString('testapp', 'migrating-secret');
		$this->assertEquals($secret, $actualSecret);

		// Encrypting on change
		$appConfig->setValueString('testapp', 'migrating-secret', $secret, sensitive: true);
		$this->assertConfigValueNotEquals('testapp', 'migrating-secret', $secret);

		// Can get in same run
		$actualSecret = $appConfig->getValueString('testapp', 'migrating-secret');
		$this->assertEquals($secret, $actualSecret);

		// Can get freshly decrypted from DB
		$newAppConfig = $this->createAppConfig();
		$actualSecret = $newAppConfig->getValueString('testapp', 'migrating-secret');
		$this->assertEquals($secret, $actualSecret);
	}

	protected function loadConfigValueFromDatabase(string $app, string $key): string|false {
		$sql = $this->connection->getQueryBuilder();
		$sql->select('configvalue')
			->from('appconfig')
			->where($sql->expr()->eq('appid', $sql->createParameter('appid')))
			->andWhere($sql->expr()->eq('configkey', $sql->createParameter('configkey')))
			->setParameter('appid', $app)
			->setParameter('configkey', $key);
		$query = $sql->executeQuery();
		$actual = $query->fetchOne();
		$query->closeCursor();

		return $actual;
	}

	protected function assertConfigKey(string $app, string $key, string|false $expected): void {
		$this->assertEquals($expected, $this->loadConfigValueFromDatabase($app, $key));
	}

	protected function assertConfigValueNotEquals(string $app, string $key, string|false $expected): void {
		$this->assertNotEquals($expected, $this->loadConfigValueFromDatabase($app, $key));
	}
}
