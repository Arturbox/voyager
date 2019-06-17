<?php

namespace TCG\Voyager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use TCG\Voyager\Database\Schema\SchemaManager;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Traits\Translatable;

class DataTable extends Model
{
    use Translatable;
    protected $table = 'data_tables';

    protected $translatable = ['name'];

    protected $fillable = [
        'name',
        'data_type_id '
    ];

    protected $guarded = [];

    public $timestamps = false;

    public function columns()
    {
        return $this->hasMany(Voyager::modelClass('DataTableRows'))->orderBy('order');
    }


    public function updateDataTable($requestData, $throw = false)
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
                $fields = $this->fields((strlen($this->model_name) != 0)
                    ? app($this->model_name)->getTable()
                    : array_get($requestData, 'name')
                );

                $requestData = $this->getRelationships($requestData, $fields);

                foreach ($fields as $field) {
                    $dataRow = $this->rows()->firstOrNew(['field' => $field]);

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

                    $relationship_column = $requestData['relationship_column_belongs_to_'.$relationship];
                    if ($requestData['relationship_type_'.$relationship] == 'hasOne' || $requestData['relationship_type_'.$relationship] == 'hasMany') {
                        $relationship_column = $requestData['relationship_column_'.$relationship];
                    }


                    if ($requestData['relationship_type_'.$relationship] == 'oneInChain'){
                        $relationshipDetails = [
                            'model'       => $requestData['relationship_chain_model_'.$relationship],
                            'table'       => $requestData['relationship_chain_table_'.$relationship],
                            'type'        => $requestData['relationship_type_'.$relationship],
                            'column'      => $relationship_column,
                            'key'         => $requestData['relationship_keyChain_'.$relationship],
                            'label'       => $requestData['relationship_chain_label_'.$relationship],
                            'pivot_table' => $requestData['relationship_pivot_table_'.$relationship],
                            'pivot'       => '1',
                            'taggable'    => isset($requestData['relationship_taggable_'.$relationship]) ? $requestData['relationship_taggable_'.$relationship] : '0',
                        ];
                    }
                    else{
                        // Build the relationship details
                        $relationshipDetails = [
                            'model'       => $requestData['relationship_model_'.$relationship],
                            'table'       => $requestData['relationship_table_'.$relationship],
                            'type'        => $requestData['relationship_type_'.$relationship],
                            'column'      => $relationship_column,
                            'key'         => $requestData['relationship_key_'.$relationship],
                            'label'       => $requestData['relationship_label_'.$relationship],
                            'pivot_table' => $requestData['relationship_pivot_table_'.$relationship],
                            'pivot'       => ($requestData['relationship_type_'.$relationship] == 'belongsToMany') ? '1' : '0',
                            'taggable'    => isset($requestData['relationship_taggable_'.$relationship]) ? $requestData['relationship_taggable_'.$relationship] : '0',
                        ];
                    }

                    $requestData['field_details_'.$relationship] = json_encode($relationshipDetails);
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
