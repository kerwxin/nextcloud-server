<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2024 Maxence Lange <maxence@artificial-owl.com>
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OC\Repair;

use OC\Core\BackgroundJobs\AppConfigLazyMigration;
use OCP\BackgroundJob\IJobList;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class AddAppConfigLazyMigrationJob implements IRepairStep {
	public function __construct(
		private IJobList $jobList,
	) {
	}

	public function getName() {
		return 'Queue a job to migrate lazy config values';
	}

	public function run(IOutput $output) {
		$this->jobList->add(AppConfigLazyMigration::class);
	}
}
