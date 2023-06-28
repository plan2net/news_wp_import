<?php
declare(strict_types=1);

namespace StudioMitte\NewsWpImport\Import;

use GeorgRinger\News\Domain\Service\CategoryImportService;
use GeorgRinger\News\Updates\PopulateCategorySlugs;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RelationImporter extends BaseImporter
{

    public function importTags(): int
    {

    }

    public function importCategories(int $pid): int
    {
        $categories = $this->wordPressRepository->getCategories();

        $items = [];
        foreach ($categories as $category) {
            $items[] = [
                'import_source' => $this->sourceIdentifier,
                'import_id' => $category['term_id'],
                'crdate' => time(),
                'tstamp' => time(),
                'hidden' => 0,
                'title' => $category['name'],
                'shortcut' => 0,
                'single_pid' => 0,
                'pid' => $pid,
                 // @todo: get field value via DB from WP
                'description' => '',
                'parentcategory' => 0
            ];
        }
        /** @var CategoryImportService $categoryService */
        $categoryService = $this->objectManager->get(CategoryImportService::class);
        $categoryService->import($items);

        $slugUpdate = GeneralUtility::makeInstance(PopulateCategorySlugs::class);
        $slugUpdate->executeUpdate();

        return count($items);
    }
}
