<?php

class PluginIserviceImportMapping extends CommonDBChild
{

    public static $itemtype = 'itemtype';
    public static $items_id = 'items_id';

    public static function createForItem(CommonDBTM $item, int $oldId): bool
    {
        $translation = new self();
        $translation->add(
            [
                'itemtype' => $item::getType(),
                'items_id' => $item->getID(),
                'old_id'   => $oldId,
            ]
        );
        return true;
    }

    public function findForOldItemID(string $itemType, int $oldId): array
    {
        $result = $this->find(
            [
                'itemtype' => $itemType,
                'old_id'   => $oldId,
            ],
            [],
            1
        );

        return count($result) ? array_shift($result) : [];
    }

}
