<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DynamicRecord extends Model
{
    protected $guarded = [];

    /**
     * Allow setting the table name dynamically at runtime.
     * 
     * @param string $tableName
     * @return $this
     */
    public function setDynamicTable(string $tableName): self
    {
        $this->setTable($tableName);
        return $this;
    }
}
