<?php

declare(strict_types=1);

namespace OCA\Testing\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\Settings\GetDeclarativeSettingsValueEvent;

/**
 * @template-implements IEventListener<GetDeclarativeSettingsValueEvent>
 */
class GetDeclarativeSettingsValueListener implements IEventListener {

	public function __construct(private IConfig $config) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof GetDeclarativeSettingsValueEvent) {
			return;
		}

		if ($event->getApp() !== 'testing') {
			return;
		}

		$value = $this->config->getUserValue($event->getUser()->getUID(), $event->getApp(), $event->getFieldId());
		$event->setValue($value);
	}
}
