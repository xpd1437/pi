<?php
/**
 * Pi Engine (http://pialog.org)
 *
 * @link            http://code.pialog.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://pialog.org
 * @license         http://pialog.org/license.txt BSD 3-Clause License
 * @package         View
 */

namespace Pi\View\Helper;

use Pi;
use Zend\View\Helper\AbstractHelper;

/**
 * Helper for building module asset URI
 *
 * Usage inside a phtml template
 *
 * ```
 *  $cssUri = $this->assetModule('css/style.css');
 *  $cssUri = $this->assetModule('css/style.css', 'demo');
 * ```
 *
 * @see Pi\Application\Service\Asset
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
class AssetModule extends AbstractHelper
{
    /**
     * Get URI of a module asset
     *
     * @param   string      $file
     * @param   string      $module
     * @param   bool|null   $appendVersion
     *
     * @return  string
     */
    public function __invoke(
        $file,
        $module = '',
        $appendVersion = null
    ) {
        //$type = $isPublic ? 'public' : 'asset';
        $module = $module ?: Pi::service('module')->current();

        // Check if customized asset available in current theme
        $customAssets = Pi::registry('theme_module_asset')->read($module);
        if (!empty($customAssets[$file])) {
            $result = $customAssets[$file];
        // Load original module asset
        } else {
            $result = Pi::service('asset')->getModuleAsset(
                $file,
                $module,
                $appendVersion
            );
        }

        return $result;
    }
}
