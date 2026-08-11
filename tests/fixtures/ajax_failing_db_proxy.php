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
 * DB proxy fixture that can force write failures for AJAX mutation tests.
 *
 * @package    mod_slideshow
 * @category   test
 * @copyright  2026 Josemaria Bolanos <admin@mako.digital>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_slideshow;

/**
 * DB proxy that can force write failures for AJAX mutation tests.
 *
 * @package    mod_slideshow
 * @category   test
 * @copyright  2026 Josemaria Bolanos <admin@mako.digital>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ajax_failing_db_proxy {
    /** @var \moodle_database Real database. */
    private $real;

    /** @var bool Force update_record to fail. */
    private $failupdate;

    /** @var bool Force delete_records to fail. */
    private $faildelete;

    /** @var bool Force execute to fail. */
    private $failexecute;

    /**
     * Create a proxy around the real database connection.
     *
     * @param \moodle_database $real Real database connection.
     * @param bool $failupdate Fail update_record calls.
     * @param bool $faildelete Fail delete_records calls.
     * @param bool $failexecute Fail execute calls.
     */
    public function __construct(
        \moodle_database $real,
        bool $failupdate = false,
        bool $faildelete = false,
        bool $failexecute = false
    ) {
        $this->real = $real;
        $this->failupdate = $failupdate;
        $this->faildelete = $faildelete;
        $this->failexecute = $failexecute;
    }

    /**
     * Update a database record, optionally forcing failure.
     *
     * @param string $table Table name.
     * @param object|array $dataobject Record.
     * @param bool $bulk Bulk flag.
     * @return bool
     */
    public function update_record($table, $dataobject, $bulk = false) {
        if ($this->failupdate) {
            return false;
        }
        return $this->real->update_record($table, $dataobject, $bulk);
    }

    /**
     * Delete matching records, optionally forcing failure.
     *
     * @param string $table Table name.
     * @param array|null $conditions Conditions.
     * @return bool
     */
    public function delete_records($table, ?array $conditions = null) {
        if ($this->faildelete) {
            return false;
        }
        return $this->real->delete_records($table, $conditions);
    }

    /**
     * Execute SQL, optionally forcing failure.
     *
     * @param string $sql SQL statement.
     * @param array|null $params Parameters.
     * @return bool
     */
    public function execute($sql, ?array $params = null) {
        if ($this->failexecute) {
            return false;
        }
        return $this->real->execute($sql, $params);
    }

    /**
     * Proxy remaining database API calls to the real connection.
     *
     * @param string $name Method name.
     * @param array $arguments Arguments.
     * @return mixed
     */
    public function __call(string $name, array $arguments) {
        return $this->real->$name(...$arguments);
    }
}
