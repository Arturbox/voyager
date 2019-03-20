<?php

namespace TCG\Voyager\Commands;

use Illuminate\Foundation\Console\ModelMakeCommand;
use Symfony\Component\Console\Input\InputOption;

class MakeModelCommand extends ModelMakeCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'voyager:make:model';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Voyager model class';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__.'/../../stubs/model.stub';
    }

    /**
     * Build the class with the given name.
     *
     * @param string $name
     *
     * @return string
     */
    protected function buildClass($name)
    {
        $stub = $this->files->get($this->getStub());

        return $this->addSoftDelete($stub)->addTranslation($stub)->addLog($stub)->replaceNamespace($stub, $name)->replaceClass($stub, $name);
    }

    /**
     * Add SoftDelete to the given stub.
     *
     * @param string $stub
     *
     * @return $this
     */
    protected function addSoftDelete(&$stub)
    {
        $traitIncl = $trait = '';

        if ($this->option('softdelete')) {
            $traitIncl = 'use Illuminate\Database\Eloquent\SoftDeletes;';
            $trait = 'use SoftDeletes;';
        }

        $stub = str_replace('//DummySDTraitInclude', $traitIncl, $stub);
        $stub = str_replace('//DummySDTrait', $trait, $stub);

        return $this;
    }


    /**
     * Add translation to the given stub.
     *
     * @param string $stub
     *
     * @return $this
     */
    protected function addTranslation(&$stub)
    {
        $traitIncl = $trait = $traitColumns = '';
        if ($this->option('translation')) {
            $traitIncl = 'use TCG\Voyager\Traits\Translatable;';
            $trait = 'use Translatable;';
            $traitColumns = 'protected $translatable = [';
            foreach ($this->option('translation') as $column){
                if (in_array($column->getType()->getName(),['varchar','text','tinytext','mediumtext','longtext'])){
                    $traitColumns .= '"'.$column->getName().'",';
                }
            }
            $traitColumns .= '];';
        }
        $stub = str_replace('//DummyySDTraitIncludeTranslatable', $traitIncl, $stub);
        $stub = str_replace('//DummyySDTraitTranslatable', $trait, $stub);
        $stub = str_replace('//DummyySDTraitTranslatablColumns', $traitColumns, $stub);
        return $this;
    }


    /**
     * Add Activity log to the given stub.
     *
     * @param string $stub
     *
     * @return $this
     */
    protected function addLog(&$stub)
    {

        $traitIncl = 'use Spatie\Activitylog\Traits\LogsActivity;';
        $trait = 'use LogsActivity;';
        $traitAttributes = 'protected static $logAttributes = ["*"];';
        $traitLogName = 'protected static $logName = "'.$this->option('log')['name'].'";';
        $stub = str_replace('//DummyyySDTraitIncludeActivitylog', $traitIncl, $stub);
        $stub = str_replace('//DummyyySDTraitActivitylogs', $trait, $stub);
        $stub = str_replace('//DummyyySDTraitActivitylogAttributes', $traitAttributes, $stub);
        $stub = str_replace('//DummyyySDTraitActivitylogName', $traitLogName, $stub);
        return $this;
    }






    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        $options = [
            ['softdelete', 'd', InputOption::VALUE_NONE, 'Add soft-delete field to Model'],
            ['translation', 'e', InputOption::VALUE_NONE, 'Add translation to Model'],
            ['log', 'g', InputOption::VALUE_NONE, 'Add activity log to Model'],
        ];

        return array_merge($options, parent::getOptions());
    }
}
