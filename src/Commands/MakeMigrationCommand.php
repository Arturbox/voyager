<?php
/**
 * Created by PhpStorm.
 * User: User
 * Date: 3/28/2019
 * Time: 2:37 PM
 */


namespace TCG\Voyager\Commands;

use Illuminate\Database\Console\Migrations\MigrateMakeCommand;
use Symfony\Component\Console\Input\InputOption;

class MakeMigrationCommand extends MigrateMakeCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'voyager:make:migration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Voyager migration class';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__.'/../../stubs/migration.stub';
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
        dd($name);
        return $this->addTranslation($stub)->replaceNamespace($stub, $name)->replaceClass($stub, $name);
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
                if (in_array($column->getType()->getName(),config('voyager.multilingual.field_types')))
                    $traitColumns .= '"'.$column->getName().'",';
            }
            $traitColumns .= '];';
        }
        $stub = str_replace('//DummyySDTraitIncludeTranslatable', $traitIncl, $stub);
        $stub = str_replace('//DummyySDTraitTranslatable', $trait, $stub);
        $stub = str_replace('//DummyySDTraitTranslatablColumns', $traitColumns, $stub);
        return $this;
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        $options = [];

        return array_merge($options, parent::getOptions());
    }
}
