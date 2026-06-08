<?php

declare(strict_types=1);

namespace OCA\BesteSchule\Command;

use OCA\BesteSchule\Service\AccountService;
use OCA\BesteSchule\Service\SyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCommand extends Command {

	public function __construct(
		private readonly AccountService $accountService,
		private readonly SyncService $syncService,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('beste_schule:sync')
			->setDescription('Trigger synchronization for a specific user')
			->addArgument('user', InputArgument::REQUIRED, 'User ID')
			->addArgument('accountId', InputArgument::OPTIONAL, 'Account ID (optional if user has only one account)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$userId = $input->getArgument('user');
		$accountId = $input->getArgument('accountId');

		$accounts = $this->accountService->getAccountsForUser($userId);
		if (empty($accounts)) {
			$output->writeln("<error>No accounts found for user $userId</error>");
			return 1;
		}

		$accountToSync = [];
		if ($accountId === null) {
			$accountToSync = $accounts;
		} else {
			foreach ($accounts as $acc) {
				if ($acc->getId() === (int)$accountId) {
					$accountToSync[] = $acc;
					break;
				}
			}
			if (empty($accountToSync)) {
				$output->writeln("<error>Account ID $accountId not found for user $userId</error>");
				return 1;
			}
		}

		foreach ($accountToSync as $account) {
			$output->writeln("<info>Syncing account: " . $account->getStudentName() . " (ID: " . $account->getId() . ")</info>");
			if ($output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
				$output->writeln("<comment>Debug logging enabled via -vvv</comment>");
			}
			try {
				$this->syncService->sync($account);
				$output->writeln("<info>Sync completed for " . $account->getStudentName() . "</info>");
			} catch (\Exception $e) {
				$output->writeln("<error>Sync failed for " . $account->getStudentName() . ": " . $e->getMessage() . "</error>");
			}
		}

		return 0;
	}
}
