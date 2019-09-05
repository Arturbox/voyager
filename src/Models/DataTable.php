<?php

namespace TCG\Voyager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use TCG\Voyager\Database\Schema\SchemaManager;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Traits\Translatable;
use TCG\Voyager\Models\DataFilter;

class DataTable extends Model
{
    use Translatable;
    protected $table = 'data_tables';

    protected $translatable = ['name'];

    protected $fillable = [
        'name',
        'data_type_id ',
        'nesthead'
    ];

    protected $guarded = [];

    public $timestamps = false;

    public function columns()
    {
        return $this->hasMany(Voyager::modelClass('DataTableRows'))->orderBy('order');
    }
    public function browseRows()
    {
        return $this->columns()->where('browse', 1);
    }

    public function readRows()
    {
        return $this->columns()->where('read', 1);
    }

    public function editRows()
    {
        return $this->columns()->where('edit', 1);
    }

    public function addRows()
    {
        return $this->columns()->where('add', 1);
    }

    public function deleteRows()
    {
        return $this->columns()->where('delete', 1);
    }



    public function dataType(){
        return $this->belongsTo(Voyager::modelClass('DataType'));
    }

    public function updateDataTable($dataType, $requestData, $throw = false)
    {
        try {
            DB::beginTransaction();

            // Prepare data
//            foreach (['generate_permissions', 'server_side','show_filters','filter_browse','filter_read','filter_update','filter_add'] as $field) {
//                if (!isset($requestData[$field])) {
//                    $requestData[$field] = 0;
//                }
//            }
            if ($this->fill($requestData)->save()) {
                $fields = $this->fields((strlen($dataType->model_name) != 0)
                    ? app($dataType->model_name)->getTable()
                    : array_get($requestData, 'name')
                );

                $requestData = $this->getRelationships($requestData, $fields);

                foreach ($fields as $field) {
                    $dataRow = $this->columns()->firstOrNew(['field' => $field]);

                    foreach (['browse', 'read', 'edit', 'add', 'delete'] as $check) {
                        $dataRow->{$check} = isset($requestData["field_{$check}_{$field}"]);
                    }

                    $dataRow->required = boolval($requestData['field_required_'.$field]);
                    $dataRow->field = $requestData['field_'.$field];
                    $dataRow->type = $requestData['field_input_type_'.$field];
                    $dataRow->details = json_decode($requestData['field_details_'.$field]);
                    $dataRow->display_name = $requestData['field_display_name_'.$field];
                    $dataRow->order = intval($requestData['field_order_'.$field]);

                    if (!$dataRow->save()) {
                        throw new \Exception(__('voyager::database.field_safe_failed', ['field' => $field]));
                    }
                }

                // Clean data_rows that don't have an associated field
                // TODO: need a way to identify deleted and renamed fields.
                //   maybe warn the user and let him decide to either rename or delete?
                $this->columns()->whereNotIn('field', $fields)->delete();

//                // It seems everything was fine. Let's check if we need to generate permissions
//                if ($this->generate_permissions) {
//                    Voyager::model('Permission')->generateFor($this->name);
//                }

                DB::commit();

                return true;
            }
        } catch (\Exception $e) {
            DB::rollBack();

            if ($throw) {
                throw $e;
            }
        }

        return false;
    }



    public function fields($name = null)
    {
        if (is_null($name)) {
            $name = $this->name;
        }

        $fields = SchemaManager::listTableColumnNames($name);

        if ($extraFields = $this->extraFields()) {
            foreach ($extraFields as $field) {
                $fields[] = $field['Field'];
            }
        }

        return $fields;
    }

    public function getRelationships($requestData, &$fields)
    {
        if (isset($requestData['relationships'])) {
            $relationships = $requestData['relationships'];
            if (count($relationships) > 0) {
                foreach ($relationships as $index => $relationship) {
                    // Push the relationship on the allowed fields
                    array_push($fields, $relationship);

                    $requestData['field_details_'.$relationship] = $requestData['relationship_details_'.$relationship];
                }
            }
        }

        return $requestData;
    }

    public function extraFields()
    {
        if (empty(trim($this->model_name))) {
            return [];
        }

        $model = app($this->model_name);
        if (method_exists($model, 'adminFields')) {
            return $model->adminFields();
        }
    }

    public function getNameFromNumber($num) {
        $numeric = $num % 26;
        $letter = chr(65 + $numeric);
        $num2 = intval($num / 26);
        if ($num2 > 0) {
            return $this->getNameFromNumber($num2 - 1) . $letter;
        } else {
            return $letter;
        }
    }


    public function reverseBySmart($dataType,$dataTableContent){
        $this->i = $iii = 0;
        $this->rowsByContent = $this->browseRows->where('details.nesthead','>',-1)->groupBy('details.nesthead')->sortKeys()->map(function ($columns) use(&$iii){
            return $columns->map(function ($column) use(&$iii){
                $iii++;
                $column->order = $iii;
                return $column;
            });
        })->collapse();

        $this->dataContent = $dataTableContent->map(function ($data){
            return $this->mergeSmartSelectedValues($data);
        });

        $this->mergedListRows = $this->rowsByContent->map(function ($column,$i) {
            return $this->mergedSelectListInRows($column,$i);
        })->collapse();

        $this->mergedListRows = $this->mergedListRows->map(function ($column) {
            return isset($column->details->relationship)?$this->fieldBindOtherField($column,$this->rowsByContent):$column;
        });

        //add empty headers
        $this->nestHeaders = collect();
        $nestParent = [];
        $nestChildrenTable = [];
        $nestChildrenTableField = [];
        $this->rowsByContent->where('details.nesthead','>',-1)->groupBy('details.nesthead')->sortKeys()->map(function ($columns,$id) use(&$nestParent,&$nestChildrenTable,&$nestChildrenTableField){
            if ($id){
                $nestHead = Voyager::model('DataType')->where('id',$this->nesthead)->first();
                $nestHeadDataTypeContent = strlen($nestHead->model_name) != 0 ? app($nestHead->model_name)->where('id',$id)->first() : call_user_func([DB::table($nestHead->name), "get"])->where('id',$id)->first();
                $nestParent[$id] = (object)['title'=>$nestHeadDataTypeContent->translate(App()->getLocale())->name, 'colspan'=> $columns->count()];
            }
            else{
                $nestParent[$id] = (object)['title'=>'', 'colspan'=> $columns->count()];
            }
            //table names
            $columns->map(function ($column) use (&$nestChildrenTable,&$nestChildrenTableField){
                if (isset($column->details->slug)){
                    $dataTypeRelation = Voyager::model('DataType')->where('slug', '=', $column->details->slug)->first();
                    if (isset($nestChildrenTable[$column->details->slug])){
                        $nestChildrenTable[$column->details->slug]->colspan = $nestChildrenTable[$column->details->slug]->colspan+1;
                    }
                    else{
                        $nestChildrenTable[$column->details->slug] = (object)[ 'title'=>$dataTypeRelation->translate(App()->getLocale())->display_name_singular, 'colspan'=> 1 ];
                    }
                }
                else{
                    $nestChildrenTable[] = (object)[ 'title'=>'', 'colspan'=> 1 ];
                }
                $nestChildrenTableField[] = (object)[ 'title'=>$column->translate(App()->getLocale())->display_name, 'colspan'=> 1 ];
            });

        });

        $this->nestHeaders->push(array_values($nestParent));
        $this->nestHeaders->push(array_values($nestChildrenTable));


        $this->hiddenRows = $this->mergedListRows->map(function ($column,$key){
            return isset($column->details->hidden)? '"#spreadsheet-'.$this->id.' thead tr:not(.jexcel_nested) td[data-x='.$column->order.']"':false;
        })->filter(function ($column){
            return $column;
        });

        if (isset($this->details->groupKeys)){
            $groups = collect($this->details->groupKeys)->map(function ($group){
                return $group->column;
            })->toArray();
            $dataByGroup = collect([]);
            $this->dataContent->groupBy($groups)->recursiveGroups($this->details->groupKeys,0,$this->mergedListRows,$dataByGroup);
            $this->dataContent = $dataByGroup;
        }


        //formula
        $this->letterFields = $this->rowsByContent->map(function ($row,$i){
            return $this->getNameFromNumber($i);
        });
        $this->rowsByContent->where("details.formula")->map(function ($row)  {
            $this->dataContent->map(function ($data) use($row){
                $this->i = $this->i+1;
                if ($data->has($row->field)){
                    $data->put($row->field, preg_replace_callback("/[a-zA-Z]+/",function ($input){
                                if ($this->letterFields->search($input[0])!==false)
                                    return $input[0].$this->i;
                                return $input[0];
                                },$row->details->formula)
                            );
                }
                return $data;
            });
            $this->i = 0;
        });

        $this->rowsByContent->where("details.formula")->map(function ($row){
            return $this->dataContent->map(function ($data) use($row){
                if (isset($column->details->formula))
                $data->{$column->field} = preg_replace_callback("/[a-zA-Z]+/",function ($input){
                if ($this->letterFields->search($input[0])!==false)
                    return $input[0].$this->i;
                return $input[0];
            },$column->details->formula);
            });
        });

        $this->showHiddenColumns = $this->mergedListRows->map(function ($item,$key) {
            return '"#spreadsheet-'.$this->id.' thead tr:not(.jexcel_nested) td[data-x='.$key.']"';
        });

        $this->groups = isset($this->details->groupKeys) ? collect($this->details->groupKeys)->map(function ($group){
            $dataTypeGroup = Voyager::model('DataType')->where('slug', '=', $group->dataType)->first();
            $dataTypeGroupContent = strlen($dataTypeGroup->model_name) != 0 ? app($dataTypeGroup->model_name)->get() : call_user_func([DB::table($dataType->name), "get"]);
            return (object)['dataContent'=>$dataTypeGroupContent,'field'=>$group->show_field, 'dataType'=> $group->dataType];
        }):false;

        return $this;
    }

    public function fieldBindOtherField($column,$columns){

        $slug = $column->details->slug;
        $dataType =  Voyager::model('DataType')->where('slug', '=', $slug)->first();
        $dataTypeContent = strlen($dataType->model_name) != 0 ? app($dataType->model_name)->get() : call_user_func([DB::table($dataType->name), "get"]);

        $columnBind = $columns->where('field',$column->details->relationship);
        $BindKey = $columnBind->keys()->first();
        $slugBind = $columnBind->first()->details->slug;
        $dataTypeBind = Voyager::model('DataType')->where('slug', '=', $slugBind)->first();
        $dataTypeContentBind = strlen($dataTypeBind->model_name) != 0 ? app($dataTypeBind->model_name)->get() : call_user_func([DB::table($dataTypeBind->name), "get"]);

        foreach ($dataTypeContentBind as $dataBind) {
            $select = (object)['tables' => [$slugBind => $dataBind->id]];
            $cloneDataTypeContent = clone $dataTypeContent;
            $bindData[$dataBind->id] = DataFilter::relatedDataFiltering($select, $cloneDataTypeContent, $slug)->map(function ($data) use ($column) {
                return ['id' => $data->id, 'name' => $data->translate(App()->getLocale())->{$column->details->row_info->column}];
            });
        }
        $column->bindData = isset($bindData) ? collect($bindData) : collect([]);
        if ($columnBind)
            $column->order_relation = $BindKey;
        return  $column;
    }

    public function mergedSelectListInRows($column,$i)
    {
        $array[$column->field] = (object)[
            'type'=>$column->type != 'relationship'?$column->type:$column->details->type,
            'display_name'=>$column->display_name,
            'details'=>$column->details,
            'order' =>$i,
        ];
        if ($column->type == 'relationship' &&  in_array($column->details->type,['dropdown','autocomplete'])){
            $dataTypeRelation = Voyager::model('DataType')->where('slug', '=', $column->details->slug)->first();
            $dataTypeRelationContent = strlen($dataTypeRelation->model_name) != 0 ? app($dataTypeRelation->model_name)->get() : call_user_func([DB::table($dataTypeRelation->name), "get"]);
            $array[$column->field]->source = $dataTypeRelationContent->map(function ($attr) use($column){
                return  ['id'=>$attr->id,'name'=>$attr->translate(App()->getLocale())->{$column->details->row_info->column}];
            });
        }
        return $array;
    }

    public function mergeSmartSelectedValues($data)
    {
        return $this->rowsByContent->map(function ($column) use ($data){
            if (array_key_exists($column->field, $data->attributes))
                return [$column->field=>$data->{$column->field}];
            elseif (isset($column->details->column) && $column->type == 'relationship')
                return [$column->field=>$data->{$column->details->column}];
            elseif($column->type == 'relationship' && isset($column->details->row_info->id)){
                $dataTypeRelation = Voyager::model('DataType')->where('slug', '=', $column->details->slug)->first();
                $data = strlen($dataTypeRelation->model_name) != 0
                    ? app($dataTypeRelation->model_name)->where('id',$column->details->row_info->id)->first()
                    : call_user_func([DB::table($dataTypeRelation->name), "get"]);
                return [$column->field=>$data->{$column->details->row_info->column}];
            }
            elseif ($column->type == 'relationship' && isset($column->details->row_info->relationshipTable) && isset($column->details->row_info->relationshipField)){
                $slug = $column->details->slug;
                $dataType =  Voyager::model('DataType')->where('slug', '=', $slug)->first();
                $dataTypeContent = strlen($dataType->model_name) != 0 ? app($dataType->model_name)->get() : call_user_func([DB::table($dataType->name), "get"]);

                $slugBind = $column->details->row_info->relationshipTable;

                $select = (object)['tables' => [$slugBind => $data->{$column->details->row_info->relationshipField}]];
                if ($result = DataFilter::relatedDataFiltering($select, $dataTypeContent, $slug)->first()){
                    return [$column->field=>$result->translate(App()->getLocale())->{$column->details->row_info->column}];
                }
                else{
                    return [$column->field=>''];
                }
            }
        })->collapse();
    }


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
