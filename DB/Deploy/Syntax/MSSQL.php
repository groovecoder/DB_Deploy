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
 *  Utility class for generating necessary Microsoft SQL Server-specific SQL commands
 *
 * @category DB
 * @package  DB_Deploy
 * @author   Luke Crouch <luke.crouch@gmail.com>
 * @license  http://www.gnu.org/licenses/lgpl-3.0.txt LGPL
 * @link     ?
 */
class DB_Deploy_Syntax_MSSQL extends DB_Deploy_Syntax
{
    /**
     * overridden method to generate dbms-specific timestamp SQL
     *
     * @return string MSSQL-specific timestamp SQL
     */
    public function generateTimestamp()
    {
        return "DATEDIFF(s, '19700101', GETDATE())";
    }
}

?>