<?php
/**
 * Copyright © Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\MagentoCloud\Command;

use Magento\MagentoCloud\DB\DumpGenerator;
use Magento\MagentoCloud\Util\BackgroundProcess;
use Magento\MagentoCloud\Util\MaintenanceModeSwitcher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Class DbDump for safely creating backup of database
 *
 * @api
 */
class DbDump extends Command
{
    const NAME = 'db-dump';

    const OPTION_REMOVE_DEFINERS = 'remove-definers';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var DumpGenerator
     */
    private $dumpGenerator;

    /**
     * @var MaintenanceModeSwitcher
     */
    private $maintenanceModeSwitcher;

    /**
     * @var BackgroundProcess
     */
    private $backgroundProcess;

    /**
     * @param DumpGenerator $dumpGenerator
     * @param LoggerInterface $logger
     * @param MaintenanceModeSwitcher $maintenanceModeSwitcher
     * @param BackgroundProcess $backgroundProcess
     */
    public function __construct(
        DumpGenerator $dumpGenerator,
        LoggerInterface $logger,
        MaintenanceModeSwitcher $maintenanceModeSwitcher,
        BackgroundProcess $backgroundProcess
    ) {
        $this->dumpGenerator = $dumpGenerator;
        $this->logger = $logger;
        $this->maintenanceModeSwitcher = $maintenanceModeSwitcher;
        $this->backgroundProcess = $backgroundProcess;

        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName(self::NAME)
            ->setDescription('Creates backup of database');

        $this->addOption(
            self::OPTION_REMOVE_DEFINERS,
            'd',
            InputOption::VALUE_NONE,
            'Remove definers from the database dump'
        );

        parent::configure();
    }

    /**
     * Creates DB dump.
     * Command requires confirmation before execution.
     *
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->isInteractive()) {
            $helper = $this->getHelper('question');

            $questionParts = [
                'The db-dump command switches the site to maintenance mode during the dump process.',
                'Your site will not receive any traffic until the operation completes.',
                'Do you wish to proceed with this process? (y/N)?',
            ];
            $question = new ConfirmationQuestion(
                implode(PHP_EOL, $questionParts),
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                return null;
            }
        }

        try {
            $this->logger->info('Starting backup.');
            $this->maintenanceModeSwitcher->enable();
            $this->backgroundProcess->kill();
            $this->dumpGenerator->create((bool)$input->getOption(self::OPTION_REMOVE_DEFINERS));
            $this->logger->info('Backup completed.');
        } catch (\Exception $exception) {
            $this->logger->critical($exception->getMessage());

            throw $exception;
        } finally {
            $this->maintenanceModeSwitcher->disable();
        }
    }
}
