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

    public function reverseBySmart($dataType,$dataTableContent){
        $this->rowsByContent = $this->browseRows;

        $this->dataContent = $dataTableContent->map(function ($data){
            return $this->mergeSmartSelectedValues($data,$this->rowsByContent);
        });
        //dd($dataTable->dataContent);

        $this->mergedListRows = $this->rowsByContent->map(function ($column,$i) {
            return $this->mergedSelectListInRows($column,$i);
        })->collapse();

        $this->mergedListRows = $this->mergedListRows->map(function ($column) {
            return isset($column->details->relationship)?$this->fieldBindOtherField($column,$this->rowsByContent):$column;
        });

        //add empty headers
        $this->nestHeaders = collect();
        $nestParent[] = $empty = (object)['title'=>'', 'colspan'=> $this->rowsByContent->where('type' ,'!=','relationship')->count()];
        $nestChildren[] = clone $empty;
        //add relationship headers
        $this->rowsByContent->where('type' ,'relationship')->groupBy('details.slug')->map(function ($item,$slug) use (&$nestParent,&$nestChildren){
            $dataTypeRelation = Voyager::model('DataType')->where('slug', '=', $slug)->first();
            $nestParent[] = (object)[ 'title'=>$dataTypeRelation->translate(App()->getLocale())->display_name_singular, 'colspan'=> $item->count() ];
            if ($item->where('details.row_info.id')->count()){
                $item->groupBy('details.row_info.id')->map(function ($itemm,$id) use($slug,&$nestChildren,$dataTypeRelation){
                    $dataTypeRelationContent = strlen($dataTypeRelation->model_name) != 0 ? app($dataTypeRelation->model_name)->where('id',$id)->first() : call_user_func([DB::table($dataTypeRelation->name), "get"])->where('id',$id)->first();
                    $nestChildren[] = [ 'title'=>$dataTypeRelationContent->translate(App()->getLocale())->{$itemm->first()->details->row_info->column}, 'colspan'=> $itemm->count() ];
                });
            }
            else{
                $nestChildren[0]->colspan = $nestChildren[0]->colspan+1;
             //   dd($nestChildren[0]->colspan);
            }
        });

        //dd($nestChildren);
        $this->nestHeaders->push($nestParent);
        if (isset($nestChildren))
            $this->nestHeaders->push($nestChildren);

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

        $this->showHiddenColumns = $this->mergedListRows->map(function ($item,$key) {
            return '"#spreadsheet-'.$this->id.' thead tr:not(.jexcel_nested) td[data-x='.$key.']"';
        });

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

    public function mergeSmartSelectedValues($data,$columns)
    {
        return $columns->map(function ($column) use ($data){
            if ($data->{$column->field})
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
