<?php

declare(strict_types=1);

namespace StudioMitte\NewsWpImport\Service;
use TYPO3\CMS\Core\DataHandling\DataHandler as CoreDataHandler;
use TYPO3\CMS\Core\Utility\StringUtility;

class DataHandler extends CoreDataHandler
{
    public function clearCache(): void
    {
        $this->start([], []);
        $this->clear_cacheCmd('all');
    }

    public function localizeRecordAndProvideNewUid(int $uid, string $table, int $languageUid): int|bool
    {
        /** @psalm-suppress InternalMethod */
        /* Need to use internal method to get an UID of localized record */
        return $this->localize(
            $table,
            $uid,
            $languageUid
        );
    }

    public function localizeRecord(int $uid, string $table, int $languageUid): void
    {
        $cmd[$table] = [
            $uid => [
                'localize' => $languageUid,
            ],
        ];
        $this->start([], $cmd);
        $this->process_cmdmap();
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function createRecord(array $data, string $table): int
    {
        $newId = StringUtility::getUniqueId('NEW');
        $this->datamap = [
            $table => [
                $newId => $data,
            ],
        ];
        $this->start($this->datamap, []);
        $this->process_datamap();

        return (int)$this->substNEWwithIDs[$newId];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function editRecord(int $recordUid, array $data, string $table): void
    {
        $this->datamap = [
            $table => [$recordUid => $data],
        ];
        $this->start($this->datamap, []);
        $this->process_datamap();
    }
}
