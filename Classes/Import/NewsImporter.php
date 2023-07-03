<?php
declare(strict_types=1);

namespace StudioMitte\NewsWpImport\Import;

use GeorgRinger\News\Domain\Service\NewsImportService;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class NewsImporter extends BaseImporter
{

    protected array $categories = [];

    public function run(int $pid): string
    {
        $this->fillRelations();

        /** @var NewsImportService $importService */
        $importService = $this->objectManager->get(NewsImportService::class);
        $settings = [
            'findCategoriesByImportSource' => $this->sourceIdentifier
        ];
        $germanPosts = $this->getGermanPosts($pid);
        //die(var_dump($germanPosts));
        $importService->import($germanPosts, [], $settings);

        $importedGermanPosts = $this->newsRepository->getNews();

        $englishPosts = 0;

        foreach ($importedGermanPosts as $germanPost) {
            $uidsOfGermanPostsThatHaveAnEnglishTranslation =
                $this->wordPressRepository->getUidOfPostsThatHaveAnEnglishTranslation();
            if (in_array((int)$germanPost['import_id'], $uidsOfGermanPostsThatHaveAnEnglishTranslation, true)) {
                $this->dataHandler->localizeRecord($germanPost['uid'], 'tx_news_domain_model_news', 1);

                $importedPost = $this->newsRepository->getLocalizedEnglishPost($germanPost['uid']);
                foreach ($importedPost as $englishPost) {
                    $data = $this->getDataToBeChangedForLocalizedEnglishPost($pid, (int)$germanPost['import_id'],);
                    $this->dataHandler->editRecord($englishPost['uid'], $data, 'tx_news_domain_model_news');
                }
                $englishPosts++;
            }
        }

        $allImportedNews = $this->newsRepository->getNews();
        //die(var_dump(count($allImportedNews)));

        return count(
                $importedGermanPosts
            ) . ' german posts and ' . $englishPosts . ' english translations were imported.';
    }

    private function getGermanPosts(int $pid): array
    {
        $items = [];
        foreach ($this->wordPressRepository->getPostsGermanLanguage() as $row) {
            $single = [
                'import_source' => $this->sourceIdentifier,
                'import_id' => $row['ID'],
                'crdate' => 0,
                'hidden' => 0,
                'type' => 3,
                'title' => $row['post_title'],
                'path_segment' => $row['post_name'],
                'pid' => $pid,
                //'media' => $this->getAttachments($row['ID']),
                'datetime' => \DateTime::createFromFormat('Y-m-d H:i:s', $row['post_date_gmt'])->getTimestamp(),
                'tstamp' => \DateTime::createFromFormat('Y-m-d H:i:s', $row['post_modified'])->getTimestamp(),
                'sys_language_uid' => 0
            ];
            $this->addContent($row['post_content'], $single);
            $this->addRelations($row['ID'], $single);

            $this->cleanup($single, $row['ID']);
            $items[] = $single;
        }

        return $items;
    }

    private function getDataToBeChangedForLocalizedEnglishPost(int $pid, int $germanImportPostId): array
    {
        $item = [];
        $row = $this->wordPressRepository->getPostEnglishLanguageWithGermanParentByGivenTheImportId(
            $germanImportPostId
        );
        foreach ($row as $entry) {
            $single = [
                'import_source' => $this->sourceIdentifier,
                'import_id' => $entry['ID'],
                'crdate' => 0,
                'hidden' => 0,
                'type' => 3,
                'title' => $entry['post_title'],
                'path_segment' => $entry['post_name'],
                'pid' => $pid,
                //'media' => $this->getAttachments($entry['ID']),
                'datetime' => \DateTime::createFromFormat('Y-m-d H:i:s', $entry['post_date_gmt'])->getTimestamp(),
                'tstamp' => \DateTime::createFromFormat('Y-m-d H:i:s', $entry['post_modified'])->getTimestamp(),
            ];
            $this->addContent($entry['post_content'], $single);
            $this->addRelations($entry['ID'], $single);

            $this->cleanup($single, $entry['ID']);

            $item = $single;
        }

        return $item;
    }

    protected function cleanup(array &$row, $id): void
    {
        $pattern = '/(<p>)?\[caption(.*?)\[\/caption\](<\/p>)?/s';
        $removals = ['<!-- /wp:more -->', '<!-- /wp:tadv/classic-paragraph -->', '<!-- wp:tadv/classic-paragraph -->'];
        foreach (['title', 'bodytext'] as $field) {
            $row[$field] = trim(str_replace($removals, '', $row[$field]));
            //$row[$field] = preg_replace($pattern, '', $row[$field], 1);
            $row[$field] = preg_replace('/(&nbsp;\s+)/', '', $row[$field]);
            //die(var_dump($row['bodytext']));
            //$this->parseBodyTextInContentElements($row[$field]);
        }
        //$this->parseBodyTextInContentElements($id, $row['bodytext']);
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

    /*protected function getAttachments(int $id): array
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
                'alt' => $row['post_title'],
                'showinpreview' => ($key === 0)
            ];
        }

        return $media;
    }

    protected function getFile(string $file): string
    {
        if (!$file) {
            return '';
        }
        $path_prefix_http = 'http://scilog.fwf.ac.at/content/uploads/';
        $path_prefix_https = 'https://scilog.fwf.ac.at/content/uploads/';

        $identifier = str_replace($path_prefix_https, '', $file);
        $identifier = str_replace($path_prefix_http, '', $identifier);

        $newPath = Environment::getPublicPath() . '/fileadmin/Scilog/' . $identifier;
        if (!is_file($newPath)) {
            $content = GeneralUtility::getUrl($file);
            if (!$content) {
                return $content;
            }

            GeneralUtility::writeFile($newPath, $content);
        }

        return '/fileadmin/Scilog/' . $identifier;
    }*/

    private function fillRelations(): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_category');
        $rows = $queryBuilder
            ->select('import_id', 'title')
            ->from('sys_category')
            ->where(
                $queryBuilder->expr()->eq(
                    'import_source',
                    $queryBuilder->createNamedParameter($this->sourceIdentifier, \PDO::PARAM_STR)
                )
            )
            ->execute()
            ->fetchAll();

        foreach ($rows as $row) {
            $this->categories[$row['title']] = $row['import_id'];
        }
    }



    private function startsWithCaption(string $bodytext): bool
    {
        return preg_match('/^(<p>)?\[caption\b/', $bodytext) === 1;
    }

    private function startsWithGallery(string $bodytext): bool
    {
        return preg_match('/^\[gallery\b/', $bodytext) === 1;
    }

    private function createFalMediaSysFileReference(int $pid, string $attachmentUid, int $newsItemUid): void
    {
        $data =
            [
                'pid' => $pid,
                'uid_local' => $attachmentUid,
                'uid_foreign' => $newsItemUid,
                'tablenames' => 'tx_news_domain_model_news',
                'fieldname' => 'fal_media',
                'table_local' => 'sys_file',
            ];
        $this->dataHandler->createRecord($data, 'sys_file_reference');
    }

    /*private function createGalleryContentElementSysFileReference(string $attachmentsUids): void
    {
        $dataTtContent =
            [
                'uid_local' => $uidLocal,
                'tx_news_related_news' => $uidNews,
                'pid' => $pid,
                'CType' => 'image-gallery',
            ];
        $dataReference =
            [
                'uid_local' => $uidLocal,
                'uid_foreign' => $uidForeign,
                'tablenames' => 'tt_content',
                'fieldname' => 'fal_media',
            ];
        $this->dataHandler->createRecord($dataTtContent, 'tt_content');
        $this->dataHandler->createRecord($dataReference, 'sys_file_reference');
    }*/
}
