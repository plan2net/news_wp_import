<?php
declare(strict_types=1);

namespace StudioMitte\NewsWpImport\Import;

//use Plan2net\news_wp_import\Classes\Repository\CustomNewsRepository;
use StudioMitte\NewsWpImport\Repository\WordPressRepository;
use StudioMitte\NewsWpImport\Repository\CustomNewsRepository;
use StudioMitte\NewsWpImport\Service\DataHandler;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

class BaseImporter
{
    protected ObjectManager $objectManager;
    protected Connection $connection;
    protected string $sourceIdentifier;
    protected WordPressRepository $wordPressRepository;
    protected CustomNewsRepository $newsRepository;
    protected DataHandler $dataHandler;

    public function __construct(string $databaseName, string $sourceIdentifier = 'news_wp_import')
    {
        $this->sourceIdentifier = $sourceIdentifier;
        $this->connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName($databaseName);
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->wordPressRepository = GeneralUtility::makeInstance(WordPressRepository::class, $databaseName);
        $this->newsRepository = GeneralUtility::makeInstance(CustomNewsRepository::class);
        $this->dataHandler = GeneralUtility::makeInstance(DataHandler::class);

    }

}
