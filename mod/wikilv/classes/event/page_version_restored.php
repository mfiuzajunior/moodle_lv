<?php
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
 * The mod_wikilv version restored event.
 *
 * @package    mod_wikilv
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_wikilv\event;
defined('MOODLE_INTERNAL') || die();

/**
 * The mod_wikilv version restored event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int pageid: id wikilv page.
 * }
 *
 * @package    mod_wikilv
 * @since      Moodle 2.7
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_version_restored extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'wikilv_versions';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventversionrestored', 'mod_wikilv');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' restored version '$this->objectid' for the page with id '{$this->other['pageid']}' " .
            "for the wikilv with course module id '$this->contextinstanceid'.";
    }

    /**
     * Return the legacy event log data.
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        return(array($this->courseid, 'wikilv', 'restore',
            'view.php?pageid=' . $this->other['pageid'], $this->other['pageid'], $this->contextinstanceid));
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/wikilv/viewversion.php', array('pageid' => $this->other['pageid'],
            'versionid' => $this->objectid));
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['pageid'])) {
            throw new \coding_exception('The pageid needs to be set in $other');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'wikilv_versions', 'restore' => 'wikilv_version');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['pageid'] = array('db' => 'wikilv_pages', 'restore' => 'wikilv_page');

        return $othermapped;
    }
}
