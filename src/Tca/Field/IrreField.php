<?php
/**
 * Created by PhpStorm.
 * User: marco
 * Date: 11.06.17
 * Time: 21:57
 */

namespace Typo3Api\Tca\Field;


use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Typo3Api\Builder\TableBuilder;
use Typo3Api\Utility\DbFieldDefinition;

class IrreField extends TcaField
{
    protected function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setRequired('foreignTable');
        $resolver->setDefaults([
            'foreignField' => 'parent_uid',
            // if foreignTakeover is true, the other table is exclusive for this relation (recommended)
            // this means hideTable will be set to true, and some other behaviors will change
            // however: you can still use the foreign table for other inline relations
            'foreignTakeover' => true,
            'minitems' => 0,
            'maxitems' => 100, // at some point, inline record editing doesn't make sense anymore
            'collapseAll' => function (Options $options) {
                return $options['maxitems'] > 5;
            },

            'dbType' => function (Options $options) {
                return DbFieldDefinition::getIntForNumberRange(0, $options['maxitems']);
            },
            'exclude' => function (Options $options) {
                return $options['minitems'] <= 0;
            },
        ]);

        $resolver->setAllowedTypes('foreignTable', ['string', TableBuilder::class]);
        $resolver->setAllowedTypes('foreignField', 'string');
        $resolver->setAllowedTypes('minitems', 'int');
        $resolver->setAllowedTypes('maxitems', 'int');

        $resolver->setNormalizer('foreignTable', function (Options $options, $foreignTable) {
            if ($foreignTable instanceof TableBuilder) {
                return $foreignTable->getTableName();
            }

            return $foreignTable;
        });
    }

    public function getFieldTcaConfig(string $tableName)
    {
        $foreignTable = $this->getOption('foreignTable');
        if (!isset($GLOBALS['TCA'][$foreignTable])) {
            throw new \RuntimeException("Configure $foreignTable before adding it in the irre configuraiton of $tableName");
        }

        $foreignTableDefinition = $GLOBALS['TCA'][$foreignTable];
        $sortby = @$foreignTableDefinition['ctrl']['sortby'] ?: @$foreignTableDefinition['ctrl']['_sortby'];
        $canBeSorted = (bool)$sortby;
        $canLocalize = (bool)@$foreignTableDefinition['ctrl']['languageField'];
        $canHide = (bool)@$foreignTableDefinition['columns']['hidden'];

        // this is the takeover part... it will modify globals which isn't so nice
        // TODO create a better spot to modify globals.. this doesn't fit here
        if ($this->getOption('foreignTakeover')) {
            // the doc states that sortby should be disabled if the table is exclusive for this relation
            // https://docs.typo3.org/typo3cms/TCAReference/8-dev/ColumnsConfig/Type/Inline.html#foreign-sortby
            if ($sortby) {
                $GLOBALS['TCA'][$foreignTable]['ctrl']['sortby'] = null;
                $GLOBALS['TCA'][$foreignTable]['ctrl']['sortby_'] = $sortby;
            }

            // ensure only this relation sees the other table
            $GLOBALS['TCA'][$foreignTable]['ctrl']['hideTable'] = true;

            // since this table can't normally be created anymore, remove creation restrictions
            ExtensionManagementUtility::allowTableOnStandardPages($foreignTable);
        }

        return [
            'type' => 'inline',
            'foreign_table' => $this->getOption('foreignTable'),
            'foreign_field' => $this->getOption('foreignField'),
            'foreign_sortby' => $sortby,
            'minitems' => $this->getOption('minitems'),
            'maxitems' => $this->getOption('maxitems'),
            'behaviour' => [
                'enableCascadingDelete' => $this->getOption('foreignTakeover'),
                'localizeChildrenAtParentLocalization' => $canLocalize
            ],
            'appearance' => [
                'collapseAll' => $this->getOption('collapseAll') ? 1 : 0,
                'useSortable' => $canBeSorted,
                'showPossibleLocalizationRecords' => $canLocalize,
                'showRemovedLocalizationRecords' => $canLocalize,
                'showAllLocalizationLink' => $canLocalize,
                'showSynchronizationLink' => $canLocalize, // potentially dangerous...
                'enabledControls' => [
                    'info' => TRUE,
                    'new' => TRUE,
                    'dragdrop' => $canBeSorted,
                    'sort' => $canBeSorted,
                    'hide' => $canHide,
                    'delete' => TRUE,
                    'localize' => $canLocalize,
                ],
            ],
        ];
    }

    public function getColumns(string $tableName): array
    {
        $columns = parent::getColumns($tableName);

        if ($this->getOption('localize') === false) {
            // remove the l10n display options
            // inline field cant be displayed as readonly
            unset($columns[$this->getOption('name')]['l10n_display']);
        }

        return $columns;
    }

    public function getDbTableDefinitions(string $tableName): array
    {
        $tableDefinitions = parent::getDbTableDefinitions($tableName);

        // define the field on the other side
        // TODO somewhere it should be checked if this field is already defined
        $foreignField = addslashes($this->getOption('foreignField'));
        $tableDefinitions[$this->getOption('foreignTable')] = [
            "`$foreignField` INT(11) DEFAULT '0' NOT NULL",
            "KEY `$foreignField`(`$foreignField`)"
        ];

        return $tableDefinitions;
    }
}