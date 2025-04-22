// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Javascript to add a custom block_delete handler
 *
 * @module     block_opencast/block_delete_handler
 * @copyright  2024 Justus Dieckmann, University of Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalFactory from 'core/modal_factory';
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Prefetch from "core/prefetch";
import {get_string} from 'core/str';
import Notification from "core/notification";

export const init = (contextid, deleteurl) => {
    Prefetch.prefetchTemplate('block_opencast/delete_block_modal');
    Prefetch.prefetchString('block_opencast', 'deletecheck_title_modal');
    const deleteButton = document.querySelector('.block_opencast a.dropdown-item.block_opencast_delete');
    deleteButton.onclick = async(e) => {
        e.preventDefault();

        const html = await Templates.render('block_opencast/delete_block_modal', {
            deleteblockurl: deleteurl
        });

        const modal = await ModalFactory.create({
            type: ModalFactory.types.CANCEL,
            body: html,
            title: await get_string('deletecheck_title_modal', 'block_opencast'),
            large: true
        });
        await modal.show();
        modal.body[0].querySelector('.block_opencast-delete-mapping').onclick = async() => {
            try {
                await Ajax.call([{
                    methodname: 'tool_opencast_unlink_series',
                    args: {contextid: contextid, ocinstanceid: -1, seriesid: 'all'}
                }])[0];
                window.location = deleteurl;
            } catch (e) {
                Notification.exception(e);
            }
        };
    };
};
