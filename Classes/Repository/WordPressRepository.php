<?php
declare(strict_types=1);

namespace StudioMitte\NewsWpImport\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class WordPressRepository
{

    protected Connection $connection;

    public function __construct(string $databaseName)
    {
        $this->connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName($databaseName);
    }

    public function getPosts(): array
    {
        $queryBuilder = $this->connection->createQueryBuilder();

        return $queryBuilder
            ->select('*')
            ->from('wp_posts')
            ->where(
                $queryBuilder->expr()->eq('post_status', $queryBuilder->createNamedParameter('publish', \PDO::PARAM_STR)),
                $queryBuilder->expr()->eq('post_type', $queryBuilder->createNamedParameter('post', \PDO::PARAM_STR))
            )
            ->orderBy('ID', 'desc')
            ->execute()
            ->fetchAll();
    }

    public function getAttachments(int $id): array
    {
        $queryBuilder = $this->connection->createQueryBuilder();

        return $queryBuilder
            ->select('guid', 'post_title', 'post_content')
            ->from('wp_posts')
            ->rightJoin(
                'wp_posts',
                'wp_postmeta',
                'wp_postmeta',
                $queryBuilder->expr()->eq('wp_posts.ID', $queryBuilder->quoteIdentifier('wp_postmeta.meta_value'))
            )
            ->where(
                $queryBuilder->expr()->eq('post_id', $queryBuilder->createNamedParameter($id, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('post_type', $queryBuilder->createNamedParameter('attachment', \PDO::PARAM_STR))
            )
            ->execute()
            ->fetchAll();
    }

    public function getRelations(int $id): array
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        return $queryBuilder
            ->select('taxonomy', 'name')
            ->from('wp_term_relationships')
            ->leftJoin(
                'wp_term_relationships',
                'wp_term_taxonomy',
                'wp_term_taxonomy',
                $queryBuilder->expr()->eq('wp_term_relationships.term_taxonomy_id', $queryBuilder->quoteIdentifier('wp_term_taxonomy.term_taxonomy_id'))
            )
            ->leftJoin(
                'wp_term_taxonomy',
                'wp_terms',
                'wp_terms',
                $queryBuilder->expr()->eq('wp_terms.term_id', $queryBuilder->quoteIdentifier('wp_term_taxonomy.term_id'))
            )
            ->where(
                $queryBuilder->expr()->eq('wp_term_relationships.object_id', $queryBuilder->createNamedParameter($id, \PDO::PARAM_INT))
            )
            ->execute()
            ->fetchAll();
    }

    public function getCategories(): array
    {
        return $this->getTaxonomyItems('category');
    }

    public function getTags(): array
    {
        return $this->getTaxonomyItems('post_tag');
    }

    private function getTaxonomyItems(string $taxonomy): array
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        return $queryBuilder
            ->select('taxonomy', 'name', 'wp_terms.term_id')
            ->from('wp_term_taxonomy')
            ->leftJoin(
                'wp_term_taxonomy',
                'wp_terms',
                'wp_terms',
                $queryBuilder->expr()->eq('wp_terms.term_id', $queryBuilder->quoteIdentifier('wp_term_taxonomy.term_id'))
            )
            ->where(
                $queryBuilder->expr()->eq('taxonomy', $queryBuilder->createNamedParameter($taxonomy, \PDO::PARAM_STR))
            )
            ->execute()
            ->fetchAll();
    }

}
