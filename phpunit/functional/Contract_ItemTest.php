<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2025 Teclib' and contributors.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

namespace tests\units;

use Contract_Item;
use DbTestCase;
use Glpi\Asset\Capacity;
use Glpi\Asset\Capacity\HasContractsCapacity;
use Glpi\Features\Clonable;
use Toolbox;

class Contract_ItemTest extends DbTestCase
{
    public function testRelatedItemHasTab()
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $this->initAssetDefinition(capacities: [new Capacity(name: HasContractsCapacity::class)]);

        $this->login(); // tab will be available only if corresponding right is available in the current session

        foreach ($CFG_GLPI['contract_types'] as $itemtype) {
            $item = $this->createItem(
                $itemtype,
                $this->getMinimalCreationInput($itemtype)
            );

            $tabs = $item->defineAllTabs();
            $this->assertArrayHasKey('Contract_Item$1', $tabs, $itemtype);
        }
    }

    public function testRelatedItemCloneRelations()
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $this->initAssetDefinition(capacities: [new Capacity(name: HasContractsCapacity::class)]);

        foreach ($CFG_GLPI['contract_types'] as $itemtype) {
            if (!Toolbox::hasTrait($itemtype, Clonable::class)) {
                continue;
            }

            $item = \getItemForItemtype($itemtype);
            $this->assertContains(Contract_Item::class, $item->getCloneRelations(), $itemtype);
        }
    }
}
