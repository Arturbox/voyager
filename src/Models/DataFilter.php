<?php
namespace TCG\Voyager\Models;

use Illuminate\Database\Eloquent\Model;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Traits\Translatable;

class DataFilter extends Model
{
    use Translatable;

    protected $table = 'data_filters';

    protected $translatable = ['display_name'];

    protected $guarded = [];

    public $timestamps = false;

    public function rowBefore()
    {
        $previous = self::where('data_type_id', '=', $this->data_type_id)->where('order', '=', ($this->order - 1))->first();
        if (isset($previous->id)) {
            return $previous->section;
        }

        return '__first__';
    }

    public function relationshipField()
    {
        return @$this->details->column;
    }

    /**
     * Check if this field is the current filter.
     *
     * @return bool True if this is the current filter, false otherwise
     */
    public function isCurrentSortField($orderBy)
    {
        return $orderBy == $this->section;
    }

    public function lastFilter()
    {
        if ($result = $this->hasMany(Voyager::modelClass('DataFilter'), 'parent_id')->orderBy('order', 'DESC')->first())
            return $result->order;
        return false;
    }

    public function children()
    {
        return $this->hasMany(Voyager::modelClass('DataFilter'), 'parent_id')
            ->with('children');
    }

    public function parentId()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function getDataType(){
        return $this->belongsTo(Voyager::modelClass('DataType'), 'data_type_id');
    }

    /**
     * Build the URL to sort data type by this field.
     *
     * @return string Built URL
     */
    public function sortByUrl($orderBy, $sortOrder)
    {
        $params = [];
        $isDesc = $sortOrder != 'asc';
        if ($this->isCurrentSortField($orderBy) && $isDesc) {
            $params['sort_order'] = 'asc';
        } else {
            $params['sort_order'] = 'desc';
        }
        $params['order_by'] = $this->section;

        return url()->current().'?'.http_build_query($params);
    }

    public function setDetailsAttribute($value)
    {
        $this->attributes['details'] = json_encode($value);
    }

    public function getDetailsAttribute($value)
    {
        return json_decode(!empty($value) ? $value : '{}');
    }


    public static function smartDropdownColumnDataFilter($column)
    {
        $slug = $column->details->slug;
        $dataType =  Voyager::model('DataType')->where('slug', '=', $slug)->first();
        $dataTypeContent = strlen($dataType->model_name) != 0 ? app($dataType->model_name)->get() : call_user_func([DB::table($dataType->name), "get"]);

        $columnBind = $this->rowsByContent->where('field',$column->details->relationship);
        $BindKey = $columnBind->keys()->first();

        $slugBind = $columnBind->first()->details->slug;
        $dataTypeBind = Voyager::model('DataType')->where('slug', '=', $slugBind)->first();
        $dataTypeContentBind = strlen($dataTypeBind->model_name) != 0 ? app($dataTypeBind->model_name)->get() : call_user_func([DB::table($dataTypeBind->name), "get"]);


        return true;
    }

    public static function getRelationData($slug,$relationSlugs,$dataTypeContent,$aaa = false)
    {
        $dataType = Voyager::model('DataType')->where('slug',$slug)->first();
        foreach ($relationSlugs->tables as $k => $value){
            $dataTypeRelation = Voyager::model('DataType')->where('slug',$k)->first();

            $field = $dataType->rows->where('type','relationship')->where('details.table',$k)->first();

            if (!$field) continue;

            if ($field->details->type == "belongsToMany"){
                foreach ($dataTypeContent as $key => &$data){
                    if ($data->belongsToMany($field->details->model,$field->details->pivot_table)->first()){
                        $relationKey = $data->belongsToMany($field->details->model,$field->details->pivot_table)->first()->pivot->getRelatedKey();
                        if (!$data->belongsToMany($field->details->model,$field->details->pivot_table)->where($relationKey,'=',$value)->get()->count()) {
                            unset($dataTypeContent[$key]);
                        }
                    } else {
                        unset($dataTypeContent[$key]);
                    }
                }
            }
            elseif ($field->details->type == "belongsTo"){
                foreach ($dataTypeContent as $key => &$data){
                    if ($data->{$field->details->column} != $value){
                        unset($dataTypeContent[$key]);
                    }
                }
            }
            elseif ($field->details->type == "hasOne"){
                foreach ($dataTypeContent as $key => &$data){
                    if (!$data->hasOne($field->details->model,$field->details->column)->where($field->getKeyName(),$value)->get()->count()){
                        unset($dataTypeContent[$key]);
                    }
                }
            }
            elseif ($field->details->type == "hasMany"){
                foreach ($dataTypeContent as $key => &$data){
                    if (!$data->hasMany($field->details->model,$field->details->column)->where($field->getKeyName(),$value)->get()->count()){
                        unset($dataTypeContent[$key]);
                    }
                }
            }
        }
        return $dataTypeContent;
    }


}
