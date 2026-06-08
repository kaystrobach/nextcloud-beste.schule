<?php

declare(strict_types=1);

namespace OCA\BesteSchule\Command;

use OCA\BesteSchule\Service\AccountService;
use OCA\BesteSchule\Service\BesteSchuleService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class JournalCommand extends Command {

	public function __construct(
		private readonly AccountService $accountService,
		private readonly BesteSchuleService $apiService,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('beste_schule:journal')
			->setDescription('Display the beste_schule api answer for the journal')
			->addArgument('user', InputArgument::REQUIRED, 'User ID')
			->addArgument('accountId', InputArgument::OPTIONAL, 'Account ID (optional if user has only one account)')
			->addOption('week', 'w', InputOption::VALUE_OPTIONAL, 'Specific ISO week (e.g. 2024-19)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$userId = $input->getArgument('user');
		$accountId = $input->getArgument('accountId');

		$accounts = $this->accountService->getAccountsForUser($userId);
		if (empty($accounts)) {
			$output->writeln("<error>No accounts found for user $userId</error>");
			return 1;
		}

		$account = null;
		if ($accountId === null) {
			if (count($accounts) > 1) {
				$output->writeln("<error>User $userId has multiple accounts. Please specify accountId.</error>");
				foreach ($accounts as $acc) {
					$output->writeln("  ID: " . $acc->getId() . " - Student: " . $acc->getStudentName());
				}
				return 1;
			}
			$account = $accounts[0];
		} else {
			foreach ($accounts as $acc) {
				if ($acc->getId() === (int)$accountId) {
					$account = $acc;
					break;
				}
			}
			if ($account === null) {
				$output->writeln("<error>Account ID $accountId not found for user $userId</error>");
				return 1;
			}
		}

		$token = $this->accountService->decryptToken($account);
		$studentId = $account->getStudentId();

		$week = $input->getOption('week');
		if ($week) {
			$output->writeln("<info>Fetching journal for week $week, student $studentId</info>");
			$journalData = $this->apiService->journalWeek($token, $studentId, $week);
		} else {
			$output->writeln("<info>Fetching journal days (7 back, 4 ahead), student $studentId</info>");
			$journalData = $this->apiService->journalDays($token, $studentId, 7, 4);
		}

		if (empty($journalData)) {
			$output->writeln("<comment>Response was empty.</comment>");
		}

		$output->writeln(json_encode($journalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

		return 0;
	}
}
