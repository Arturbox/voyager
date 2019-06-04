<?php

namespace TCG\Voyager\Models;

use Illuminate\Database\Eloquent\Model;

class DataTable extends Model
{
    protected $table = 'data_tables';
    protected $guarded = [];

    protected $timestamps = false;


    public function relationshipField()
    {
        return @$this->details->column;
    }



    public function setDetailsAttribute($value)
    {
        $this->attributes['details'] = json_encode($value);
    }

    public function getDetailsAttribute($value)
    {
        return json_decode(!empty($value) ? $value : '{}');
    }
}
