<?php
/**
 * Abstract Exporter Model Class
 *
 * @section LICENSE
 * This file is created by vianetz <info@vianetz.com>.
 * The code is distributed under the GPL license.
 *
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@vianetz.com so we can send you a copy immediately.
 *
 * @category    Vianetz
 * @package     Vianetz\Exporter
 * @author      Christoph Massmann, <C.Massmann@vianetz.com>
 * @link        http://www.vianetz.com
 * @copyright   Copyright (c) since 2006 vianetz - Dipl.-Ing. C. Massmann (http://www.vianetz.com)
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GENERAL PUBLIC LICENSE
 */

abstract class Vianetz_Exporter_Model_Abstract extends Mage_Core_Model_Abstract implements Vianetz_Exporter_Model_Interface
{
    /**
     * @var string
     */
    protected $_urlCampaignParameter = '';

    /**
     * @return array
     */
    abstract public function getAttributesToExport();

    /**
     * @return Vianetz_Exporter_Model_Abstract
     */
    abstract protected function _exportData();

    /**
     * Do the export run.
     *
     * @return Vianetz_Exporter_Model_Abstract
     */
    public function run()
    {
        $this->_validateInput();

        $this->_exportData();

        $this->_processOutput();

        return $this;
    }

    /**
     * @return string
     */
    public function getExportPath()
    {
        return Mage::getBaseDir('var') . DS . 'export';
    }

    /**
     * @return string
     */
    public function getExportFilename()
    {
        return md5(microtime()) . '.csv';
    }

    /**
     * Get export file with path.
     *
     * @return string
     */
    public final function getExportFilePath()
    {
        return $this->getExportPath() . DS . $this->getExportFilename();
    }

    /**
     * @return \Mage_Catalog_Model_Resource_Product_Collection
     */
    public function getRowCollection()
    {
        return Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToFilter('status', 1)
            ->addAttributeToFilter('visibility', 4)
            ->addAttributeToSelect('*')
            ->addUrlRewrite();
    }

    /**
     * @throws \Vianetz_Exporter_Model_Exception
     */
    protected function _validateInput()
    {
        if (isset($_SERVER['REQUEST_METHOD'])) {
            die('This script cannot be run from Browser. This is the shell script.');
        }

        $rowCollection = $this->getRowCollection();
        if (empty($rowCollection) === true) {
            throw new Vianetz_Exporter_Model_Exception('No row collection to export specified.');
        }

        $attributesToExport = $this->getAttributesToExport();
        if (empty($attributesToExport) === true) {
            throw new Vianetz_Exporter_Model_Exception('No attributes to export defined.');
        }
    }

    /**
     * @return array
     */
    protected function _getCsvHeaders()
    {
        return array_keys($this->getAttributesToExport());
    }

    /**
     * @param \Mage_Catalog_Model_Product $product
     * @param string $attributeName
     *
     * @return string
     */
    protected function _getAttributeText(Mage_Catalog_Model_Product $product, $attributeName)
    {
        if (empty($attributeName) === true) {
            return '';
        }

        $methodName = '_format' . ucfirst($attributeName);
        if (method_exists($this, $methodName) === true) {
            return $this->$methodName($product);
        }

        if ($product->hasData($attributeName) === false) {
            return '';
        }

        $attributeText = $product->getAttributeText($attributeName);
        if (empty($attributeText) === false) {
            return $this->_filterTextValue($attributeText);
        }

        return $this->_filterTextValue($product->getData($attributeName));
    }

    /**
     * Return categories with delimiter.
     *
     * @todo refactor this
     *
     * @param Mage_Catalog_Model_Product
     * @param string $categoryDelimiter Delimiter for each category.
     *
     * @return string
     */
    protected function _formatCategoryPath(Mage_Catalog_Model_Product $product, $categoryDelimiter = '-')
    {
        $categoriesString = '';
        foreach ($product->getCategoryIds() as $categoryId) {
            $category = Mage::getModel('catalog/category')
                ->load($categoryId);
            $cpath = explode('/', $category->getPath());
            $katpath = '';
            for ($b = 0; count($cpath) > $b; $b++) {
                if ($b > 1 && $b < (count($cpath) - 1)) {
                    $tmpcategory = Mage::getModel('catalog/category')->load($cpath[$b]);
                    $katpath .= $tmpcategory->getName() . ' ' . $categoryDelimiter . ' ';
                }
            }

            $categoriesString .= $katpath . $category->getName();
            break;
        }

        return $categoriesString;
    }

    /**
     * Return product image url.
     *
     * @param Mage_Catalog_Model_Product $product
     *
     * @return string
     */
    protected function _formatImageUrl(Mage_Catalog_Model_Product $product)
    {
        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $product->getImage();
    }

    /**
     * Filter bad characters from textual string.
     *
     * @param string $valueString
     *
     * @return string
     */
    protected function _filterTextValue($valueString)
    {
        $bad = array('"', "\r\n", "\n", "\r", "\t");
        $good = array("", " ", " ", " ", "");

        return str_replace($bad, $good, $valueString);
    }

    /**
     * Return product url with campaign parameters.
     *
     * @param Mage_Catalog_Model_Product $product
     *
     * @return string
     */
    protected function _formatProductUrl(Mage_Catalog_Model_Product $product)
    {
        return $product->getProductUrl() . '?' . $this->_urlCampaignParameter;
    }

    /**
     * This method is called to process data after export, e.g. upload to some FTP server or something like this.
     */
    protected function _processOutput()
    {
        // Do nothing by default..
        return;
    }

    /**
     * Helper method to upload exported file to ftp server.
     *
     * @param string $ftpHost
     * @param string $ftpUser
     * @param string $ftpPassword
     * @param int $ftpPort
     *
     * @throws Varien_Io_Exception
     *
     * @return boolean returns true whether the operation was successful or not
     */
    protected function _uploadFileToFtp($ftpHost, $ftpUser, $ftpPassword, $ftpPort = 21)
    {
        try {
            $ftp = new Varien_Io_Ftp();
            $ftp->open(
                array(
                    'host' => $ftpHost,
                    'user' => $ftpUser,
                    'password' => $ftpPassword,
                    'port' => $ftpPort
                )
            );

            $localFile = fopen($this->getExportFilePath(), 'r');
            $isSuccessful = $ftp->write($this->getExportFilename(), $localFile);
            $ftp->close();
        } catch (Exception $exception) {
            Mage::logException($exception);
            $isSuccessful = false;
        }

        if ($isSuccessful === false) {
            return false;
        }

        return true;
    }
}
