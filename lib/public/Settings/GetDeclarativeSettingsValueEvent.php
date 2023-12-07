<?php

namespace OCP\Settings;

use Exception;
use OCP\EventDispatcher\Event;
use OCP\IUser;

/**
 * @psalm-import-type DeclarativeSettingsValueTypes from IDeclarativeSettingsForm
 *
 * @since 29.0.0
 */
class GetDeclarativeSettingsValueEvent extends Event {
	/**
	 * @var ?DeclarativeSettingsValueTypes
	 */
	private mixed $value = null;

	/**
	 * @since 29.0.0
	 */
	public function __construct(
		private IUser $user,
		private string $app,
		private string $formId,
		private string $fieldId,
	) {
		parent::__construct();
	}

	/**
	 * @since 29.0.0
	 */
	public function getUser(): IUser {
		return $this->user;
	}

	/**
	 * @since 29.0.0
	 */
	public function getApp(): string {
		return $this->app;
	}

	/**
	 * @since 29.0.0
	 */
	public function getFormId(): string {
		return $this->formId;
	}

	/**
	 * @since 29.0.0
	 */
	public function getFieldId(): string {
		return $this->fieldId;
	}

	/**
	 * @since 29.0.0
	 */
	public function setValue(mixed $value): void {
		$this->value = $value;
	}

	/**
	 * @return DeclarativeSettingsValueTypes
	 * @throws Exception
	 *
	 * @since 29.0.0
	 */
	public function getValue(): mixed {
		if ($this->value === null) {
			throw new Exception('Value not set');
		}

		return $this->value;
	}
}
