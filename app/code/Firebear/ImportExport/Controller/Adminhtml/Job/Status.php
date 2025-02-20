<?php
/**
 * @copyright: Copyright © 2017 Firebear Studio. All rights reserved.
 * @author   : Firebear Studio <fbeardev@gmail.com>
 */

namespace Firebear\ImportExport\Controller\Adminhtml\Job;

use Firebear\ImportExport\Controller\Adminhtml\Job as JobController;

/**
 * Class Status
 *
 * @package Firebear\ImportExport\Controller\Adminhtml\Job
 */
class Status extends JobController
{
    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $resultJson = $this->resultFactory->create($this->resultFactory::TYPE_JSON);
        if ($this->getRequest()->isAjax()) {
            $file = $this->getRequest()->getParam('file');
            $counter = (int)$this->getRequest()->getParam('number', 0);
            $console = $this->helper->scopeRun($file, $counter);
            return $resultJson->setData(
                [
                    'console' => $console
                ]
            );
        }
    }
}
