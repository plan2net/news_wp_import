<?php
declare(strict_types=1);

namespace StudioMitte\NewsWpImport\Import;

use GeorgRinger\News\Domain\Service\NewsImportService;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\File\BasicFileUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class NewsImporter extends BaseImporter
{

    protected array $categories = [];

    public function run(int $pid): int
    {
        $this->fillRelations();
        $items = [];

        foreach ($this->wordPressRepository->getPosts() as $row) {
            $single = [
                'import_source' => $this->sourceIdentifier,
                'import_id' => $row['ID'],
                'crdate' => 0,
                'hidden' => 0,
                'type' => 0,
                'title' => $row['post_title'],
                'path_segment' => $row['post_name'],
                'pid' => $pid,
                'media' => $this->getAttachements($row['ID']),
                'datetime' => \DateTime::createFromFormat('Y-m-d H:i:s', $row['post_date_gmt'])->getTimestamp(),
                'tstamp' => \DateTime::createFromFormat('Y-m-d H:i:s', $row['post_modified'])->getTimestamp()
            ];
            $this->addContent($row['post_content'], $single);
            $this->addRelations($row['ID'], $single);

            $this->cleanup($single);
            $items[] = $single;
        }

        /** @var NewsImportService $importService */
        $importService = $this->objectManager->get(NewsImportService::class);
        $settings = [
            'findCategoriesByImportSource' => $this->sourceIdentifier
        ];
        $importService->import($items, [], $settings);

        return count($items);
    }

    protected function cleanup(array &$row): void
    {
        $removals = ['<!-- /wp:more -->', '<!-- /wp:tadv/classic-paragraph -->', '<!-- wp:tadv/classic-paragraph -->'];
        foreach (['title', 'bodytext'] as $field) {
            $row[$field] = trim(str_replace($removals, '', $row[$field]));
        }
    }

    protected function addRelations(int $id, array &$row): void
    {
        $relations = $this->wordPressRepository->getRelations($id);
        $categories = $tags = [];
        foreach ($relations as $relation) {
            if ($relation['taxonomy'] === 'category') {
                $categories[] = $relation['name'];
            }
        }

        if (!empty($categories)) {
            $row['categories'] = [];
            foreach ($categories as $categoryName) {
                $id = $this->categories[$categoryName] ?? 0;
                if ($id) {
                    $row['categories'][$id] = $id;
                }
            }
        }
    }

    protected function addContent(string $content, &$row): void
    {
        $teaserSearchTerms = ['<p><!--more--></p>', '<!--more-->'];
        foreach ($teaserSearchTerms as $teaserSearchTerm) {
            $more = mb_strpos($content, $teaserSearchTerm);
            if ($more !== false) {
                $teaser = strip_tags(mb_substr($content, 0, $more));
                $row['teaser'] = trim($teaser);

                $content = mb_substr($content, $more + strlen($teaserSearchTerm));
                $content = str_replace('<p> </p>', '', $content);
                $row['bodytext'] = trim($content);
                return;
            }
        }
        $row['bodytext'] = trim($content);
    }

    protected function getAttachements(int $id)
    {
        $media = [];

        foreach ($this->wordPressRepository->getAttachments($id) as $key => $row) {
            $file = $this->getFile($row['guid']);
            if (!$file) {
                continue;
            }
            $media[] = [
                'image' => $file,
                'title' => $row['post_title'],
                'alt' => $row['post_content'],
                'showinpreview' => ($key === 0)
            ];
        }

        return $media;
    }

    protected function getFile(string $file)
    {
        if (!$file) {
            return '';
        }
        $info = pathinfo($file);
        $basicFileUtility = GeneralUtility::makeInstance(BasicFileUtility::class);

        $newName = $basicFileUtility->cleanFileName($info['filename']) . '_' . GeneralUtility::shortMD5($file) . '.' . $info['extension'];
        $newPath = Environment::getPublicPath() . '/fileadmin/import/blog/' . $newName;
        if (!is_file($newPath)) {
            $content = GeneralUtility::getUrl($file);
            if (!$content) {
                return $content;
            }

            GeneralUtility::writeFile($newPath, $content);
        }

        return 'fileadmin/import/blog/' . $newName;
    }

    private function fillRelations(): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_category');
        $rows = $queryBuilder
            ->select('import_id', 'title')
            ->from('sys_category')
            ->where(
                $queryBuilder->expr()->eq('import_source', $queryBuilder->createNamedParameter($this->sourceIdentifier, \PDO::PARAM_STR))
            )
            ->execute()
            ->fetchAll();

        foreach ($rows as $row) {
            $this->categories[$row['title']] = $row['import_id'];
        }
    }
}
