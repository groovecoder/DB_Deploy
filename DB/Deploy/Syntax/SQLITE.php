<?php
/**
 * DB_Deploy change-management tool
 *
 * @category DB
 * @package  DB_Deploy
 * @author   Luke Crouch <luke.crouch@gmail.com>
 * @license  http://www.gnu.org/licenses/lgpl-3.0.txt LGPL
 * @link     ?
 */

/**
 *  Utility class for generating necessary SQLite-specific SQL commands
 *
 * @category DB
 * @package  DB_Deploy
 * @author   Luke Crouch <luke.crouch@gmail.com>
 * @license  http://www.gnu.org/licenses/lgpl-3.0.txt LGPL
 * @link     ?
 */
class DB_Deploy_Syntax_SQLITE extends DB_Deploy_Syntax
{
    /**
     * overridden method to generate dbms-specific timestamp SQL
     *
     * @return string SQLITE-specific timestamp SQL
     */
    public function generateTimestamp()
    {
        return "strftime('%s','now')";
    }
}

?>