<?php
/**
 * Abstract CSV Export Model
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

abstract class Vianetz_Exporter_Model_Csv extends Vianetz_Exporter_Model_Abstract
{
    /**
     * CSV delimiter character.
     *
     * @var string
     */
    protected $_csvDelimiter = ';';

    /**
     * CSV enclosure character.
     *
     * @var string
     */
    protected $_csvEnclosure = '"';

    /**
     * Process the export data.
     *
     * @return Vianetz_Exporter_Model_Csv
     */
    protected function _exportData()
    {
        $io = new Varien_Io_File();
        $io->setAllowCreateFolders(true);
        $io->open(array('path' => $this->getExportPath()));
        $io->streamOpen($this->getExportFilePath(), 'w+');
        $io->streamLock(true);

        $io->streamWriteCsv($this->_getCsvHeaders(), $this->_csvDelimiter);
        $rowCollection = $this->getRowCollection();
        foreach ($rowCollection as $rowItem) {
            $itemData = array();
            foreach ($this->getAttributesToExport() as $alias => $attribute) {
                $itemData[] = $this->_getAttributeText($rowItem, $attribute);
            }
            $io->streamWriteCsv($itemData, $this->_csvDelimiter, $this->_csvEnclosure);
        }

        $io->close();

        return $this;
    }
}
