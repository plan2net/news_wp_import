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
        //Import german posts from WP
        $germanPosts = $this->getGermanPosts($pid);
        $importService->import($germanPosts, [], $settings);

        //Get german imported news
        $importedGermanPosts = $this->newsRepository->getNews();

        $englishPosts = 0;
        foreach ($importedGermanPosts as $germanPost) {
            //Get uids of german posts that have a translation
            $uidsOfGermanPostsThatHaveAnEnglishTranslation =
                $this->wordPressRepository->getUidOfPostsThatHaveAnEnglishTranslation();
            //Check if the current post has an english translation
            if (in_array((int)$germanPost['import_id'], $uidsOfGermanPostsThatHaveAnEnglishTranslation, true)) {
                //If yes, localize it (doesn't work at the moment)
                $this->dataHandler->localize('tx_news_domain_model_news', $germanPost['uid'], 1);
                //Retrieve the current localised post
                $importedPost = $this->newsRepository->getLocalizedEnglishPost($germanPost['uid']);
                //Overwrite the data of the localised post with the Worpress information
                foreach ($importedPost as $englishPost) {
                    $data = $this->getDataToBeChangedForLocalizedEnglishPost($pid, (int)$germanPost['import_id'],);
                    $this->dataHandler->editRecord($englishPost['uid'], $data, 'tx_news_domain_model_news');
                }
                $englishPosts++;
            }
        }

        return count(
                $importedGermanPosts
            ) . ' german posts and ' . $englishPosts . ' english translations were imported.';
    }

    private function getGermanPosts(int $pid): array
    {
        //Media and attachment related functions were commented out because of trying a different approach
        //The original import any image that comes first and sets it as fal_media, even if its in a gallery
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
            $row[$field] = preg_replace('/(&nbsp;\s+)/', '', $row[$field]);
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

    private function parseBodyTextInContentElements($id, string $bodytext): void
    {
        $patternImage = '/(<p>)?\[caption(.*?)\[\/caption\](<\/p>)?/s';
        $patternGallery = '/\[gallery[^]]*ids="([^"]+)"[^]]*\]/';

        $numberOfImages = preg_match_all($patternImage, $bodytext);
        $numberOfGalleries = preg_match_all($patternGallery, $bodytext);
        /*
        Here can be checked how many images and galleries each posts has
        var_dump(
        'ID ' . $id . ' Number of images is ' . $numberOfImages . ' and number of galleries is ' . $numberOfGalleries . '.'
        );*/

        if ($this->startsWithCaption($bodytext)) {
            $pattern = '/^(?:<p>)?\[caption id="attachment_(\d+)"[^]]*\](.*?)\[\/caption\](?:<\/p>\R)?/s';
            preg_match($pattern, $bodytext, $matches);

            if (count($matches) > 0) {
                //Get the import attachemnt uid from the caption tag
                $attachmentUid = $matches[1];
                //Create sys_file_reference record to create the fal_media relation
                //$this->createFalMediaSysFileReference

                //Clean up the first caption after creating the relationship
                $bodytext = preg_replace($pattern, '', $bodytext);
                $bodytext = preg_replace('/^\s*\R/m', '', $bodytext);
            }
        }
        if ($this->startsWithGallery($bodytext)) {
            $pattern = '/^\[gallery[^]]*ids="([^"]+)"[^]]*\](?:\R\s*)*/m';
            preg_match($pattern, $bodytext, $matches);

            if (count($matches) > 0) {
                //Get the import uids from the media that has to be added to the gallery conent element
                $attachmentUids = explode(',', $matches[1]);

                //Here should be the tt_content and sys file refference created
                //$this->createGalleryContentElementSysFileReference

                //Clean up the first gallery tag after creating the relationship
                $bodytext = preg_replace('/^\[gallery[^]]*\](?:\R\s*)*/m', '', $bodytext, 1);
                $bodytext = trim($bodytext);
            }
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

    private function createFalMediaSysFileReference(int $pid, string $fileUid, int $newsItemUid): void
    {
        $data =
            [
                'pid' => $pid,
                'uid_local' => $fileUid,
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
