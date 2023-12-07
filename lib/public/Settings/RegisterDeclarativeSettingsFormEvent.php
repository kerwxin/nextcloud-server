<?php

namespace OCP\Settings;

use OCP\EventDispatcher\Event;

/**
 * @psalm-import-type DeclarativeSettingsFormSchemaWithoutValues from IDeclarativeSettingsForm
 *
 * @since 29.0.0
 */
class RegisterDeclarativeSettingsFormEvent extends Event {
	/**
	 * @since 29.0.0
	 */
	public function __construct(private IDeclarativeManager $manager) {
		parent::__construct();
	}

	/**
	 * @param DeclarativeSettingsFormSchemaWithoutValues $schema
	 * @since 29.0.0
	 */
	public function registerSchema(string $app, array $schema): void {
		$this->manager->registerSchema($app, $schema);
	}
}
