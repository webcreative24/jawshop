<?php
/**
 * @copyright: Copyright © 2019 Firebear Studio. All rights reserved.
 * @author : Firebear Studio <fbeardev@gmail.com>
 */

namespace Firebear\ImportExport\Model\Export;

use DateTime;
use Firebear\ImportExport\Model\ExportJob\Processor;
use Firebear\ImportExport\Traits\Export\Entity as ExportTrait;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Serialize\Serializer\Json as Serializer;
use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\Export\AbstractEntity;
use Magento\ImportExport\Model\Export\Factory as CollectionFactory;
use Magento\ImportExport\Model\ResourceModel\CollectionByPagesIteratorFactory;
use Magento\Rule\Model\ConditionFactory;
use Magento\Rule\Model\ActionFactory;
use Magento\SalesRule\Model\ResourceModel\Rule\Collection as SalesRuleCollection;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Sales Rule export
 */
class SalesRule extends AbstractEntity implements EntityInterface
{
    use ExportTrait;

    /**
     * Entity collection
     *
     * @var AbstractCollection
     */
    protected $_entityCollection;

    /**
     * Collection factory
     *
     * @var CollectionFactory
     */
    protected $_collectionFactory;

    /**
     * Condition factory
     *
     * @var ConditionFactory
     */
    protected $conditionFactory;

    /**
     * Action factory
     *
     * @var ActionFactory
     */
    protected $actionFactory;

    /**
     * Json serializer
     *
     * @var Serializer
     */
    protected $serializer;

    /**
     * Field list
     *
     * @var array
     */
    protected $fields = [
        'rule_id',
        'name',
        'code',
        'uses_per_coupon',
        'description',
        'from_date',
        'to_date',
        'conditions_serialized',
        'actions_serialized',
        'uses_per_customer',
        'customer_group_ids',
        'is_active',
        'stop_rules_processing',
        'sort_order',
        'simple_action',
        'discount_amount',
        'discount_qty',
        'simple_free_shipping',
        'apply_to_shipping',
        'times_used',
        'is_rss',
        'coupon_type',
        'use_auto_generation',
        'website_ids',
        'store_labels'
    ];

    /**
     * Initialize export
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param CollectionFactory $collectionFactory
     * @param CollectionByPagesIteratorFactory $resourceColFactory
     * @param ConditionFactory $conditionFactory
     * @param ActionFactory $actionFactory
     * @param LoggerInterface $logger
     * @param ConsoleOutput $output
     * @param Serializer $serializer
     * @param array $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        CollectionFactory $collectionFactory,
        CollectionByPagesIteratorFactory $resourceColFactory,
        ConditionFactory $conditionFactory,
        ActionFactory $actionFactory,
        LoggerInterface $logger,
        ConsoleOutput $output,
        Serializer $serializer,
        array $data = []
    ) {
        $this->_logger = $logger;
        $this->output = $output;
        $this->serializer = $serializer;
        $this->_collectionFactory = $collectionFactory;
        $this->conditionFactory = $conditionFactory;
        $this->actionFactory = $actionFactory;

        parent::__construct(
            $scopeConfig,
            $storeManager,
            $collectionFactory,
            $resourceColFactory,
            $data
        );
    }

    /**
     * Export process
     *
     * @return array
     * @throws LocalizedException
     */
    public function export()
    {
        //Execution time may be very long
        set_time_limit(0);

        $this->addLogWriteln(__('Begin Export'), $this->output);
        $this->addLogWriteln(__('Scope Data'), $this->output);

        $collection = $this->_getEntityCollection();
        $this->_prepareEntityCollection($collection);
        $this->_exportCollectionByPages($collection);
        // create export file
        return [
            $this->getWriter()->getContents(),
            $this->_processedEntitiesCount,
            $this->lastEntityId
        ];
    }

    /**
     * Retrieve entity collection
     *
     * @return AbstractCollection
     */
    protected function _getEntityCollection()
    {
        if (null === $this->_entityCollection) {
            $this->_entityCollection = $this->_collectionFactory->create(
                SalesRuleCollection::class
            );
        }
        return $this->_entityCollection;
    }

    /**
     * Apply filter to collection
     *
     * @param AbstractCollection $collection
     * @return AbstractCollection
     * @throws LocalizedException
     * @throws \Exception
     */
    protected function _prepareEntityCollection(AbstractCollection $collection)
    {
        if (!empty($this->_parameters[Processor::LAST_ENTITY_ID]) &&
            $this->_parameters[Processor::LAST_ENTITY_SWITCH] > 0
        ) {
            $collection->addFieldToFilter(
                'main_table.rule_id',
                ['gt' => $this->_parameters[Processor::LAST_ENTITY_ID]]
            );
        }

        $entity = $this->getEntityTypeCode();
        $fields = [];
        $columns = $this->getFieldColumns();
        foreach ($columns['sales_rule'] as $field) {
            $fields[$field['field']] = $field['type'];
        }

        $collection = $this->addFiltersToCollection($collection, $entity, $fields);
        return $collection;
    }

    /**
     * Entity type code getter
     *
     * @return string
     */
    public function getEntityTypeCode()
    {
        return 'sales_rule';
    }

    /**
     * Retrieve entity field columns
     *
     * @return array
     * @throws LocalizedException
     */
    public function getFieldColumns()
    {
        $options = [];
        foreach ($this->fields as $field) {
            $type = 'text';
            $options[$this->getEntityTypeCode()][] = [
                'field' => $field,
                'type' => $type,
                'select' => []
            ];
        }
        return $options;
    }

    /**
     * Export one item
     *
     * @param AbstractModel $item
     * @return void
     * @throws LocalizedException
     */
    public function exportItem($item)
    {
        $data = [];
        $this->lastEntityId = $item->getId();

        foreach ($this->fields as $field) {
            $data[$field] = $item->getData($field);
        }

        $data['website_ids'] = implode(',', $data['website_ids'] ? $data['website_ids'] : []);
        $data['customer_group_ids'] = implode(',', $data['customer_group_ids'] ? $data['customer_group_ids'] : []);

        if (!empty($data['conditions_serialized'])) {
            $conditions = $this->serializer->unserialize($data['conditions_serialized']);
            if (is_array($conditions)) {
                $data['conditions_serialized'] = $this->serializer->serialize(
                    $this->prepareConditions($conditions)
                );
            }
        }

        if (!empty($data['actions_serialized'])) {
            $actions = $this->serializer->unserialize($data['actions_serialized']);
            if (is_array($actions)) {
                $data['actions_serialized'] = $this->serializer->serialize(
                    $this->prepareActions($actions)
                );
            }
        }

        $labels = [];
        foreach ($item->getStoreLabels() as $store => $label) {
            $labels[] = $store . '=' . $label;
        }
        $data['store_labels'] = implode(Import::DEFAULT_GLOBAL_MULTI_VALUE_SEPARATOR, $labels);

        $row = $this->changeRow($data);
        $this->getWriter()->writeRow($row);
        $this->_processedEntitiesCount++;
    }

    /**
     * Prepare row conditions
     *
     * @param array $conditions
     * @return array
     */
    protected function prepareConditions(array $conditions)
    {
        if (!empty($conditions['type'])) {
            if ($this->validateModel($conditions['type'])) {
                if (!empty($conditions['attribute'])) {
                    $conditions['value'] = $this->prepareAttributeValue($conditions);
                }
            }
        }

        if (!empty($conditions['conditions']) && is_array($conditions['conditions'])) {
            foreach ($conditions['conditions'] as $key => $condition) {
                $conditions['conditions'][$key] = $this->prepareConditions($condition);
            }
        }
        return $conditions;
    }

    /**
     * Prepare row actions
     *
     * @param array $actions
     * @return array
     */
    protected function prepareActions(array $actions)
    {
        if (!empty($actions['type'])) {
            if ($this->validateModel($actions['type'])) {
                if (!empty($actions['attribute'])) {
                    $actions['value'] = $this->prepareActionAttributeValue($actions);
                }
            }
        }

        if (!empty($actions['conditions']) && is_array($actions['conditions'])) {
            foreach ($actions['conditions'] as $key => $condition) {
                $actions['conditions'][$key] = $this->prepareConditions($condition);
            }
        }
        return $actions;
    }

    /**
     * Prepare conditions attribute value
     *
     * @param array $conditions
     * @return string|array
     */
    protected function prepareAttributeValue($conditions)
    {
        $condition = $this->conditionFactory->create($conditions['type']);
        $attributes = $condition->loadAttributeOptions()->getAttributeOption();

        if (isset($attributes[$conditions['attribute']])) {
            $condition->setAttribute($conditions['attribute']);
            if (in_array($condition->getInputType(), ['select', 'multiselect'])) {
                // reload options flag
                $condition->unsetData('value_select_options');
                $condition->unsetData('value_option');

                $options = $condition->getValueOption();
                if (is_array($conditions['value'])) {
                    foreach ($conditions['value'] as $key => $value) {
                        $conditions['value'][$key] = $options[$value];
                    }
                } else {
                    $conditions['value'] = $options[$conditions['value']];
                }
            }
        }
        return $conditions['value'];
    }

    /**
     * Prepare actions attribute value
     *
     * @param array $actions
     * @return string|array
     */
    protected function prepareActionAttributeValue($actions)
    {
        $condition = $this->actionFactory->create($actions['type']);
        $attributes = $condition->loadAttributeOptions()->getAttributeOption();

        if (isset($attributes[$actions['attribute']])) {
            $condition->setAttribute($actions['attribute']);
            if (in_array($condition->getInputType(), ['select', 'multiselect'])) {
                // reload options flag
                $condition->unsetData('value_select_options');
                $condition->unsetData('value_option');

                $options = $condition->getValueOption();
                if (is_array($actions['value'])) {
                    foreach ($actions['value'] as $key => $value) {
                        $actions['value'][$key] = $options[$value];
                    }
                } else {
                    $actions['value'] = $options[$actions['value']];
                }
            }
        }
        return $actions['value'];
    }

    /**
     * Validate conditions model
     *
     * @param string $model
     * @return bool
     */
    protected function validateModel($model)
    {
        return class_exists($model);
    }

    /**
     * Retrieve entity field for filter
     *
     * @return array
     * @throws LocalizedException
     */
    public function getFieldsForFilter()
    {
        $options = [];
        foreach ($this->getFieldsForExport() as $field) {
            $options[] = [
                'label' => $field,
                'value' => $field
            ];
        }
        return [$this->getEntityTypeCode() => $options];
    }

    /**
     * Retrieve entity field for export
     *
     * @return array
     * @throws LocalizedException
     */
    public function getFieldsForExport()
    {
        return $this->fields;
    }

    /**
     * Retrieve header columns
     *
     * @return array
     */
    protected function _getHeaderColumns()
    {
        return $this->changeHeaders($this->fields);
    }

    /**
     * Retrieve attributes codes which are appropriate for export
     *
     * @return array
     */
    protected function _getExportAttrCodes()
    {
        return [];
    }
}
