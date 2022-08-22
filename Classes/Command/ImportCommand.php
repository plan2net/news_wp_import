<?php
declare(strict_types=1);

namespace StudioMitte\NewsWpImport\Command;

use StudioMitte\NewsWpImport\Import\NewsImporter;
use StudioMitte\NewsWpImport\Import\RelationImporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\GeneralUtility;


class ImportCommand extends Command
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this
            ->setDescription('Import WordPress Posts')
            ->addArgument(
                'database',
                InputArgument::REQUIRED,
                'Name of WP database'
            )->addArgument(
                'pid',
                InputArgument::REQUIRED,
                'Page Id'
            );
    }

    /**
     * Executes the command for adding the lock file
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());

        $database = $input->getArgument('database');
        $name = 'news_wp_importer_' . $database;
        $pid = (int)$input->getArgument('pid');

        $relationImporter = GeneralUtility::makeInstance(RelationImporter::class, $database, $name);
        $count = $relationImporter->importCategories($pid);
        $io->success(sprintf('Imported %s categories', $count));

        $importer = GeneralUtility::makeInstance(NewsImporter::class, $database, $name);
        $counter = $importer->run($pid);

        $io->success(sprintf('Imported %s blog posts', $counter));

        return 0;
    }

}
