<?php
namespace App\Shell;

use Cake\Console\Shell;
use Cake\I18n\I18n;
use Cake\ORM\TableRegistry;
use League\Flysystem\Adapter\Local;

/**
 * MigrateFromCoreTranslation shell command.
 */
class MigrateFromCoreTranslation extends Shell
{

    /**
     * Main method for migration data from table i18n used with default core Translate behavior config.
     *
     * TODO: Make it work w/ customized table names
     *
     * @return bool|int Success or error code.
     */
    public function main()
    {
        $i18nTable = TableRegistry::get('i18n');

        //Make sure default is default
        i18n::locale(i18n::defaultLocale());

        $allFks = $i18nTable->find();
        $numberOfFks = $allFks->count();
        $numberOfFksLength = strlen($numberOfFks);

        $this->out('There is a total of: ' . $numberOfFks . ' translations to handle!', 0);
        $this->hr(1);

        foreach ($allFks->toArray() as $number => $fk) {
            try {
                if (!empty($fk['content'])) {
                    $currentLocale = $fk['locale'];
                    $currentTable = TableRegistry::get($fk['model']);
                    $currentField = $fk['field'];
                    $currentValue = $fk['content'];

                    I18n::locale($currentLocale);

                    try {
                        $currentEntity = $currentTable->get($fk['foreign_key']);

                        $currentEntity->_locale = $currentLocale;
                        $currentEntity->{$currentField} = $currentValue;

                        $currentTable->save($currentEntity);

                        $this->_io->overwrite($number, 0, $numberOfFksLength);
                    } catch (\Exception $e) {
                        $this->hr(1);
                        $this->err('Error in getting the entity with i18n ID: ' . $fk['id']);
                        $this->hr(1);
                    }
                }
            } catch (\Exception $e) {
                $this->hr(1);
                $this->err('Exception found on translation w/ ID: ' . $fk['id']);
                $this->hr(1);
                $this->err($e->getMessage());
            }
        }
        $this->hr(1);
        $this->out('Done migrating from core translate behavior to ShadowTranslate');

        return true;
    }
}
