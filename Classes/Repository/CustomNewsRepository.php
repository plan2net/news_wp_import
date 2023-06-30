<?php

//namespace Plan2net\news_wp_import\Classes\Repository;
namespace StudioMitte\NewsWpImport\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CustomNewsRepository
{
    protected ConnectionPool $connectionPool;

    public function __construct()
    {
        $this->connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
    }

    public function getNews(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(
            'tx_news_domain_model_news'
        );
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder
            ->select('*')
            ->from('tx_news_domain_model_news')
            ->where(
                $queryBuilder->expr()->eq(
                    'type',
                    $queryBuilder->createNamedParameter(3, Connection::PARAM_INT)
                )
            )
            ->execute()
            ->fetchAllAssociative();
    }

    public function getLocalizedEnglishPost(int $germanParentUid): array {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(
            'tx_news_domain_model_news'
        );
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder
            ->select('*')
            ->from('tx_news_domain_model_news')
            ->where(
                $queryBuilder->expr()->eq(
                    'l10n_parent',
                    $queryBuilder->createNamedParameter($germanParentUid, Connection::PARAM_INT)
                )
            )
            ->execute()
            ->fetchAllAssociative();
    }
}
