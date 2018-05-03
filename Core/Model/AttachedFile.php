<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2018 Carlos Garcia Gomez  <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\Core\Model;

use finfo;

/**
 * Class to manage attached files.
 *
 * @author Francesc Pineda Segarra <francesc.pineda.segarra@gmail.com>
 */
class AttachedFile extends Base\ModelClass
{

    use Base\ModelTrait;

    /**
     * Date.
     *
     * @var string
     */
    public $date;

    /**
     * Contains the file name.
     *
     * @var string
     */
    public $filename;

    /**
     * Hour.
     *
     * @var string
     */
    public $hour;

    /**
     * Primary key.
     *
     * @var int
     */
    public $idfile;

    /**
     * Content the mime content type.
     *
     * @var string
     */
    public $mimetype;

    /**
     * Contains the relative path to file.
     *
     * @var string
     */
    public $path;

    /**
     * The size of the file in bytes.
     *
     * @var int
     */
    public $size;

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->date = date('d-m-Y');
        $this->hour = date('H:i:s');
        $this->size = 0;
    }

    /**
     * Remove the model data from the database.
     *
     * @return bool
     */
    public function delete()
    {
        if (parent::delete()) {
            @unlink(FS_FOLDER . DIRECTORY_SEPARATOR . $this->path);
            return true;
        }

        return false;
    }

    /**
     * Returns the name of the column that is the primary key of the model.
     *
     * @return string
     */
    public static function primaryColumn()
    {
        return 'idfile';
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName()
    {
        return 'attached_files';
    }

    public function test()
    {
        if (!file_exists(FS_FOLDER . DIRECTORY_SEPARATOR . 'MyFiles' . DIRECTORY_SEPARATOR . $this->path)) {
            self::$miniLog->alert(self::$i18n->trans('file-not-found'));
            return false;
        }

        if (empty($this->filename)) {
            $this->move();
        }

        return parent::test();
    }

    protected function move()
    {
        $this->filename = $this->path;

        $path = 'MyFiles' . DIRECTORY_SEPARATOR . date('Y' . DIRECTORY_SEPARATOR . 'm', strtotime($this->date));
        if (!file_exists(FS_FOLDER . DIRECTORY_SEPARATOR . $path)) {
            mkdir(FS_FOLDER . DIRECTORY_SEPARATOR . $path, 0777, true);
        }

        $basePath = FS_FOLDER . DIRECTORY_SEPARATOR . 'MyFiles';
        if (rename($basePath . DIRECTORY_SEPARATOR . $this->path, FS_FOLDER . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR . $this->filename)) {
            $this->path = $path . DIRECTORY_SEPARATOR . $this->filename;
            $this->size = filesize(FS_FOLDER . DIRECTORY_SEPARATOR . $this->path);

            $finfo = new finfo();
            $this->mimetype = $finfo->file(FS_FOLDER . DIRECTORY_SEPARATOR . $this->path, FILEINFO_MIME_TYPE);
        }
    }
}
