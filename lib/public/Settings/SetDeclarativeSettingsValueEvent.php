<?php

namespace OCP\Settings;

use OCP\EventDispatcher\Event;
use OCP\IUser;

/**
 * @psalm-import-type DeclarativeSettingsValueTypes from IDeclarativeSettingsForm
 *
 * @since 29.0.0
 */
class SetDeclarativeSettingsValueEvent extends Event {
	/**
	 * @param DeclarativeSettingsValueTypes $value
	 * @since 29.0.0
	 */
	public function __construct(
		private IUser $user,
		private string $app,
		private string $formId,
		private string $fieldId,
		private mixed $value,
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
	public function getValue(): mixed {
		return $this->value;
	}
}
