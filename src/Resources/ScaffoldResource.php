<?php

namespace Solutionforest\FilamentScaffold\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Solutionforest\FilamentScaffold\Resources\ScaffoldResource\Pages;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;


if (!defined('STDIN')) {
    define('STDIN', fopen('php://stdin', 'r'));
}

class ScaffoldResource extends Resource
{

    /********************************************
     * !! Controls the navigation settings like:
     * GROUP, ICON & SORT
     * 
     * configure: navigation.php
     ********************************************/
    use \App\Traits\HasNavigationConfig;

    // protected static ?string $navigationIcon = 'fas-layer-group';

    // /********************************************
    //  * Group name in the 'navigation bar'
    //  * @var string|null
    //  */
    // protected static ?string $navigationGroup = 'System';
    // protected static ?int $navigationSort = 99;


    /********************************************
     * Plural label for the resource
     * @var string|null
     */
    protected static ?string $pluralModelLabel = 'Scaffold';

    protected static ?string $navigationLabel = 'Scaffold Manager';

    /********************************************
     * Singular label for the resource
     * @var string|null
     */
    protected static ?string $modelLabel = 'Scaffold';
    protected static bool $shouldRegisterNavigation = true;

    
    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                /********************************************
                 * TABLE NAME, MODEL NAME, RESOURCE NAME
                 */
                Forms\Components\Card::make('Table & Resource Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([

                                Forms\Components\TextInput::make('Table Name')
                                    ->reactive()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        $modelName = str_replace('_', '', ucwords($state, '_'));
                                        $set('Model', 'app\\Models\\' . $modelName);
                                        $set('Resource', 'app\\Filament\\Resources\\' . $modelName . 'Resource');
                                        $set('Choose Table', $state);
                                    })
                                    ->required(),

                                Forms\Components\Select::make('Choose Table')
                                    ->options(self::getAllTableNames())
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        $tableName = self::getAllTableNames()[$state];
                                        $tableColumns = self::getTableColumns($tableName);
                                        $modelName = str_replace('_', '', ucwords($tableName, '_'));
                                        $set('Table Name', $tableName);
                                        $set('Model', 'app\\Models\\' . $modelName);
                                        $set('Resource', 'app\\Filament\\Resources\\' . $modelName . 'Resource');
                                        $set('Table', $tableColumns);
                                    }),
                            ]),
    
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('Model')
                                    ->default('app\\Models\\')
                                    ->live(onBlur: true),
                                Forms\Components\TextInput::make('Resource')
                                    ->default('app\\Filament\\Resources\\')
                                    ->live(onBlur: true),
                            ]),
                    ])
                    ->columnSpan(2),
    
                /********************************************
                 * GENERATION OPTIONS
                 */
                Forms\Components\Card::make('Generation Options')
                    ->schema([
                        Forms\Components\Checkbox::make('create_resource')
                            ->default(true)
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Generate Filament resource files (list, create, edit pages)'),
                            
                        Forms\Components\Checkbox::make('create_model')
                            ->default(true)
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Create an Eloquent model for the table'),
                            
                        Forms\Components\Checkbox::make('simple_resource')
                            ->default(true)
                            ->label('Simple Resource')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Generate modal-based forms instead of separate pages'),
                            
                        Forms\Components\Checkbox::make('create_migration')
                            ->default(true)
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Create migration file (only needed for new tables)'),
                            
                        Forms\Components\Checkbox::make('create_factory')
                            ->default(true)
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Generate factory for test/demo data generation'),
                            
                        Forms\Components\Checkbox::make('create_controller')
                            ->default(false)
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Create traditional Laravel controller'),
                            
                        Forms\Components\Checkbox::make('run_migrate')
                            ->default(true)
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Automatically run migration after creation')
                            ->hidden(fn (Forms\Get $get) => !$get('create_migration')),
                            
                        Forms\Components\Checkbox::make('create_route')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Add basic routes to routes/web.php'),
                            // ->hidden(fn (Forms\Get $get) => !$get('create_controller')),
                            
                        Forms\Components\Checkbox::make('create_policy')
                            ->default(true)
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Generate authorization policy (requires Filament Shield)')
                            ->hidden(fn () => !class_exists(\BezhanSalleh\FilamentShield\FilamentShield::class)),
                            
                        Forms\Components\Checkbox::make('create_api')
                            ->label('Create API')
                            ->default(true)
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Generate API endpoints (requires API Service)')
                            ->hidden(fn () => !class_exists(\Rupadana\ApiService\ApiService::class))
                    ])
                    ->columns(2)
                    ->columnSpan(1),

                /********************************************
                 * TABLE STRUCTURE
                 */
                Forms\Components\Card::make('Table Structure')
                    ->schema([
                        Forms\Components\Repeater::make('Table')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Field Name')
                                    ->required()
                                    ->default(fn ($record) => $record['name'] ?? ''),
                                Forms\Components\TextInput::make('translation'),
                                Forms\Components\Select::make('type')
                                    ->native(false)
                                    ->searchable()
                                    ->options([
                                        'string' => 'string',
                                        'integer' => 'integer',
                                        'bigInteger' => 'bigInteger',
                                        'text' => 'text',
                                        'float' => 'float',
                                        'double' => 'double',
                                        'decimal' => 'decimal',
                                        'boolean' => 'boolean',
                                        'date' => 'date',
                                        'time' => 'time',
                                        'datetime' => 'dateTime',
                                        'timestamp' => 'timestamp',
                                        'char' => 'char',
                                        'mediumText' => 'mediumText',
                                        'longText' => 'longText',
                                        'tinyInteger' => 'tinyInteger',
                                        'smallInteger' => 'smallInteger',
                                        'mediumInteger' => 'mediumInteger',
                                        'json' => 'json',
                                        'jsonb' => 'jsonb',
                                        'binary' => 'binary',
                                        'enum' => 'enum',
                                        'ipAddress' => 'ipAddress',
                                        'macAddress' => 'macAddress',
                                    ])
                                    ->default(fn ($record) => $record['type'] ?? 'string')
                                    ->reactive(),
                                Forms\Components\Checkbox::make('nullable')
                                    ->inline(false)
                                    ->default(true),
                                    // ->default(fn ($record) => $record['nullable'] ?? false),
                                Forms\Components\Select::make('key')
                                    ->default('')
                                    ->options([
                                        '' => 'NULL',
                                        'primary' => 'Primary',
                                        'unique' => 'Unique',
                                        'index' => 'Index',
                                    ])
                                    ->default(fn ($record) => $record['key'] ?? ''),
                                Forms\Components\TextInput::make('default')
                                    ->default(fn ($record) => $record['default'] ?? ''),
                                Forms\Components\Textarea::make('comment')
                                    ->autosize()
                                    ->default(fn ($record) => $record['comment'] ?? ''),
                            ])
                            ->columns(7)
                    ])
                    ->columnSpan('full'),
    
                /********************************************
                 * MIGRATION ADDITIONAL FEATURES
                 */
                Forms\Components\Card::make('Migration Additional Features')
                    ->schema([
                        Forms\Components\Checkbox::make('Created_at & Updated_at')
                            ->label('Created_at & Updated_at timestamps')
                            ->default(true)
                            ->inline(),
                        Forms\Components\Checkbox::make('soft_delete')
                            ->label('soft_delete (recycle bin)')
                            ->default(true)
                            ->inline(),

                    ])
                    ->columns(3)
                    ->columnSpan('full'),
            ])
            ->columns(3);
    }


    public static function getAllTableNames(): array
    {
        $tables = DB::select('SHOW TABLES');

        return array_map('current', $tables);
    }

    public static function getTableColumns($tableName)
    {
        $columns = DB::select('SHOW COLUMNS FROM ' . $tableName);
        $columnDetails = [];

        $typeMapping = [
            'varchar' => 'string',
            'int' => 'integer',
            'bigint' => 'bigInteger',
            'text' => 'text',
            'float' => 'float',
            'double' => 'double',
            'decimal' => 'decimal',
            'bool' => 'boolean',
            'date' => 'date',
            'time' => 'time',
            'datetime' => 'dateTime',
            'timestamp' => 'timestamp',
            'char' => 'char',
            'mediumtext' => 'mediumText',
            'longtext' => 'longText',
            'tinyint' => 'tinyInteger',
            'smallint' => 'smallInteger',
            'mediumint' => 'mediumInteger',
            'json' => 'json',
            'jsonb' => 'jsonb',
            'binary' => 'binary',
            'enum' => 'enum',
            'ipaddress' => 'ipAddress',
            'macaddress' => 'macAddress',
        ];

        $keyMapping = [
            'PRI' => 'primary',
            'UNI' => 'unique',
            'MUL' => 'index',
        ];

        foreach ($columns as $column) {
            if ($column->Field === 'id' || $column->Field === 'ID' || $column->Field === 'created_at' || $column->Field === 'updated_at' || $column->Field === 'deleted_at') {
                continue;
            }

            $type = preg_replace('/\(.+\)/', '', $column->Type);
            $type = preg_split('/\s+/', $type)[0];

            $key = $column->Key;

            $translatedType = $typeMapping[$type] ?? $type;
            $translatedKey = $keyMapping[$key] ?? $key;

            $columnDetails[] = [
                'name' => $column->Field,
                'type' => $translatedType,
                'nullable' => $column->Null === 'YES',
                'key' => $translatedKey,
                'default' => $column->Default,
                'comment' => '',
            ];
        }

        return $columnDetails;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\CreateScaffold::route('/'),
        ];
    }

    public static function getFileName($path)
    {
        $fileNameWithExtension = basename($path);
        $fileName = pathinfo($fileNameWithExtension, PATHINFO_FILENAME);

        return $fileName;
    }

    public static function generateFiles(array $data)
    {
        $basePath = base_path();

        $modelName = self::getFileName($data['Model']);

        $resourceName = self::getFileName($data['Resource']);

        chdir($basePath);
        $migrationPath = null;
        $resourcePath = null;
        $modelPath = null;
        $controllerPath = null;

        /********************************************
         * MIGRATION FILE
         */
        if ($data['create_migration'] ?? false) {
            Artisan::call('make:migration', [
                'name' => 'create_' . $data['Table Name'] . '_table',
                '--no-interaction' => true,
            ]);
            $output = Artisan::output();
            if (strpos($output, 'Migration') !== false) {
                preg_match('/\[([^\]]+)\]/', $output, $matches);
                $migrationPath = $matches[1] ?? null;
            }
        }

        if ($data['create_factory']) {
            Artisan::call('make:factory', [
                'name' => $data['Table Name'] . 'Factory',
                '--no-interaction' => true,
            ]);
        }

        /********************************************
         * CREATE MODEL
         */
        if ($data['create_model'] ?? false) {
            try {
                $result = Artisan::call('make:model', [
                    'name' => $modelName,
                    '--no-interaction' => true,
                ]);
                
                // Success is indicated by return code 0
                if ($result !== 0) {
                    throw new \RuntimeException("Model creation failed with code: " . $result);
                }
                
                $modelPath = app_path('Models/' . $modelName . '.php');
                if (!file_exists($modelPath)) {
                    throw new \RuntimeException("Model file was not created at: " . $modelPath);
                }
                
                // Now overwrite with your custom content
                self::overwriteModelFile($modelPath, $data);
                
            } catch (\Exception $e) {
                Log::error('Model creation error: ' . $e->getMessage());
                throw $e;
            }
        }


        /********************************************
         * CREATE RESOURCE FILE
         * ! this part is not supported if you have cluster resources
         */
        if ($data['create_resource'] ?? false) {
            Log::info('Starting creation of Filament resource.', [
                'resourceName' => $resourceName,
                'simpleResource' => $data['simple_resource'] ?? false,
            ]);

            $command = [
                'name' => $resourceName,
                '--generate' => true,
                '--view' => true,
                '--force' => true,
                '--no-interaction' => true,
            ];

            /**************************
             * --simple (modal type)
             */
            if ($data['simple_resource'] ?? false) {
                $command['--simple'] = true;
            }

            try {
                Artisan::call('make:filament-resource', $command);
                $output = Artisan::output();

                Log::info('Filament resource command output:', ['output' => $output]);

                if (preg_match('/\[([^\]]+)\]/', $output, $matches)) {
                    $resourcePath = $matches[1];
                    Log::info('Resource file path detected from output.', ['resourcePath' => $resourcePath]);
                } else {
                    Log::warning('Could not detect resource file path from command output.');
                    $resourcePath = null;
                }
            } catch (\Exception $e) {
                Log::error('Error while creating Filament resource.', [
                    'exception_message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'command_args' => $command,
                ]);
                throw $e;
            }
        }


        /********************************************
         * CREATE CONTROLLER
         */
        if ($data['create_controller'] ?? false) {
            Artisan::call('make:controller', [
                'name' => $data['Table Name'] . 'Controller',
                '--model' => $modelName,
                '--resource' => true,
                '--no-interaction' => true,
            ]);
            $output = Artisan::output();
            preg_match('/\[([^\]]+)\]/', $output, $matches);
            $controllerPath = $matches[1] ?? null;
        }

        /********************************************
         * POLICY FILE (For Permissions)
         */
        if ($data['create_policy'] ?? false) {
            $modelName = self::getFileName($data['Model']);
            Artisan::call('make:policy', [
                'name' => $modelName . 'Policy',
                '--model' => $modelName,
                '--no-interaction' => true,
            ]);
            $output = Artisan::output();
            if (strpos($output, 'Policy') !== false) {
                preg_match('/\[([^\]]+)\]/', $output, $matches);
                $policyPath = $matches[1] ?? null;
                if ($policyPath) {
                    self::updatePolicyFile($policyPath, $modelName);
                    // Log::info("Policy file created and updated at: $policyPath");
                    /********************************************
                     * SUCCESS NOTIFICATION
                     */
                    Notification::make()
                        ->success()
                        ->persistent()
                        ->title('Scaffold with Policy Created Successfully!')
                        ->body('A new policy file has been successfully created for your model. Please configure the permissions for the new policy.')
                        ->icon('heroicon-o-shield-check')
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('view')
                                ->label('Configure Permissions')
                                ->button()
                                ->url(\BezhanSalleh\FilamentShield\Resources\RoleResource::getUrl(), shouldOpenInNewTab: true),
                            \Filament\Notifications\Actions\Action::make('close')
                                ->color('gray')
                                ->close(),
                        ])
                        ->send()
                        ->sendToDatabase(\App\Filament\Resources\VariableStorerResource::getCurrentLoggedUserNotifRecipient());
                }
            }
        }



        /********************************************
         * EXECUTE THE CREATING OF ROUTE
         * IF create_route is Check
         */
        if ($data['create_route'] ?? false) {
            $controllerName = self::getFileName($controllerPath);
            self::addRoutes($data, $controllerName);
        }

        self::overwriteResourceFile($resourcePath, $data);
        self::overwriteMigrationFile($migrationPath, $data);
        self::overwriteModelFile($modelPath, $data);
        self::overwriteControllerFile($controllerPath, $data);

        /********************************************
         * AFTER FILE/DB GENERATION, RUN THIS ARTISAN COMMANDS:
         */
        $commands = [
            'cache:clear',
            'config:cache',
            'config:clear',
            'route:cache',
            'route:clear',
            'icons:cache',
            'filament:cache-components'
        ];

        $commandErrors = [];

        foreach ($commands as $command) {
            $fullCommand = "php artisan $command";
            $descriptorspec = [
                0 => ["pipe", "r"], //stdin
                1 => ["pipe", "w"], //stdout
                2 => ["pipe", "w"]  //stderr
            ];

            $process = proc_open($fullCommand, $descriptorspec, $pipes, base_path());

            if (is_resource($process)) {
                fclose($pipes[0]);
                $output = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                $error = stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                $return_value = proc_close($process);

                if ($return_value !== 0) {
                    Log::error("Error running artisan command: $fullCommand", [
                        'error' => $error,
                        'output' => $output
                    ]);
                    $commandErrors[] = $fullCommand;
                }
            }
        }


        /********************************************
         * Create API with https://github.com/rupadana/filament-api-service IF installed
         */
        if ($data['create_api'] && class_exists(\Rupadana\ApiService\ApiService::class)) {
            $resourcePath = $data['Resource'] ?? null;

            if ($resourcePath) {
                $resourceClass = str_replace(['/', '\\'], '\\', $resourcePath);
                $resourceClass = preg_replace('/^app\\\\/i', 'App\\', $resourceClass);
                $resourceClassName = class_basename($resourceClass);
                $apiServiceName = str_replace('Resource', '', $resourceClassName);

                if (class_exists($resourceClass)) {
                    try {
                        //default panel ID to skip interactive prompt
                        $defaultPanelId = \Filament\Facades\Filament::getDefaultPanel()->getId();

                        //API service generator
                        Artisan::call('make:filament-api-service', [
                            'resource' => $apiServiceName,
                            '--panel' => $defaultPanelId,
                            '--no-interaction' => true,
                        ]);
                        $output = Artisan::output();

                        if (str_contains($output, 'created') || str_contains($output, 'generated')) {
                            Notification::make()
                                ->success()
                                ->persistent()
                                ->title('API Service Created Successfully!')
                                ->body(new \Illuminate\Support\HtmlString("
                                    API service has been generated for: <b>{$resourceClassName}</b><br><br>
                                    Generated files location:<br>
                                    <b>app/Filament/Resources/{$resourceClassName}/Api</b><br><br>
                                    <small><pre>" . e($output) . "</pre></small>
                                "))
                                ->icon('heroicon-o-code-bracket')
                                ->send()
                                ->sendToDatabase(\App\Filament\Resources\VariableStorerResource::getCurrentLoggedUserNotifRecipient());
                        } else {
                            Notification::make()
                                ->warning()
                                ->persistent()
                                ->title('API Service Generation Issue')
                                ->body(new \Illuminate\Support\HtmlString("
                                    There was an issue generating the API service for: <b>{$resourceClassName}</b><br><br>
                                    <small><pre>" . e($output) . "</pre></small>
                                "))
                                ->icon('heroicon-o-exclamation-triangle')
                                ->send()
                                ->sendToDatabase(\App\Filament\Resources\VariableStorerResource::getCurrentLoggedUserNotifRecipient());
                        }
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('API Service Generation Failed')
                            ->body("Unexpected error: " . $e->getMessage())
                            ->send()
                            ->sendToDatabase(\App\Filament\Resources\VariableStorerResource::getCurrentLoggedUserNotifRecipient());
                    }
                } else {
                    Notification::make()
                        ->danger()
                        ->title('Resource Class Not Found')
                        ->body("The class `{$resourceClass}` does not exist. Please generate the resource first.")
                        ->send()
                        ->sendToDatabase(\App\Filament\Resources\VariableStorerResource::getCurrentLoggedUserNotifRecipient());
                }
            } else {
                Notification::make()
                    ->danger()
                    ->title('Missing Resource Input')
                    ->body('The Resource input is required to generate the API service.')
                    ->send()
                    ->sendToDatabase(\App\Filament\Resources\VariableStorerResource::getCurrentLoggedUserNotifRecipient());
            }
        }


        if (empty($commandErrors)) {

            /********************************************
             * SUCCESS NOTIFICATION
             */
            // $resourceClickLink = "\\App\\Filament\\Resources\\" . $resourceName;
            Notification::make()
                ->success()
                ->persistent()
                ->title('Scaffold created')
                ->body('The scaffold resource has been created successfully.')
                ->icon('heroicon-o-cube-transparent')
                // ->actions([
                //     \Filament\Notifications\Actions\Action::make('view')
                //         ->button()
                //         ->url(class_exists($resourceClickLink) ? $resourceClickLink::getUrl() : '#', shouldOpenInNewTab: true),
                //     \Filament\Notifications\Actions\Action::make('close')
                //         ->color('gray')
                //         ->close(),
                // ])
                ->send()
                ->sendToDatabase(\App\Filament\Resources\VariableStorerResource::getCurrentLoggedUserNotifRecipient());
        } else {
            /********************************************
             * ERROR
             */
            Notification::make()
                ->title("Error running commands")
                ->body("Check logs for more details.")
                ->danger()
                ->send();
        }

    }


    public static function overwriteResourceFile($resourceFile, $data)
    {
        $modelName = self::getFileName($data['Model']);

        if (file_exists($resourceFile)) {
            $content = file_get_contents($resourceFile);

            $formSchema = self::generateFormSchema($data);
            $tableSchema = self::generateTableSchema($data);
            $useClassChange = <<<EOD
                use App\\Models\\$modelName;
                EOD;

            $classChange = <<<EOD
                protected static ?string \$model = $modelName::class;
                EOD;

            $formFunction = <<<EOD
                public static function form(Form \$form): Form
                    {
                        return \$form
                            ->schema([
                                $formSchema
                            ]);
                    }
                EOD;

            $tableFunction = <<<EOD
                public static function table(Table \$table): Table
                    {
                        return \$table
                            ->columns([
                                $tableSchema
                            ])
                            ->filters([
                                //
                            ])
                            ->actions([
                                Tables\Actions\ViewAction::make(),
                                Tables\Actions\EditAction::make(),
                            ])
                            ->bulkActions([
                                Tables\Actions\BulkActionGroup::make([
                                    Tables\Actions\DeleteBulkAction::make(),
                                ]),
                            ]);
                    }
                EOD;

            $content = preg_replace('/use\s+App\\\\Models\\\\.*?;/s', $useClassChange, $content);
            $content = preg_replace('/protected static\s+\?string\s+\$model\s*=\s*[^\;]+;/s', $classChange, $content);
            $content = preg_replace('/public static function form.*?{.*?}/s', $formFunction, $content);
            $content = preg_replace('/public static function table.*?{.*?}/s', $tableFunction, $content);

            file_put_contents($resourceFile, $content);
        }
    }

    public static function generateFormSchema($data)
    {
        $fields = [];
        foreach ($data['Table'] as $column) {
            $fields[] = "Forms\Components\TextInput::make('{$column['name']}')->required()";
        }

        return implode(",\n", $fields);
    }

    public static function generateTableSchema($data)
    {
        $columns = [];
        foreach ($data['Table'] as $column) {
            $columns[] = "Tables\Columns\TextColumn::make('{$column['name']}')->sortable()->searchable()";
        }

        return implode(",\n", $columns);
    }

    public static function overwriteMigrationFile($filePath, $data)
    {
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);

            $upPart = self::generateUp($data);
            $upFunction = <<<EOD
                public function up(): void
                    {
                        Schema::create('{$data['Table Name']}', function (Blueprint \$table) {
                            \$table->id();
                            $upPart;
                    }
                EOD;

            $downFunction = <<<EOD
                public function down()
                    {
                        Schema::dropIfExists('{$data['Table Name']}');
                    }
                EOD;

            $content = preg_replace('/public function up.*?{.*?}/s', $upFunction, $content);
            $content = preg_replace('/public function down.*?{.*?}/s', $downFunction, $content);

            file_put_contents($filePath, $content);
        }
        if ($data['run_migrate'] == true) {
            Artisan::call('migrate');
        }
    }

    public static function generateUp(array $data): string
    {
        $fields = array_map(
            fn (array $column): string => self::generateColumnDefinition($column),
            $data['Table']
        );

        if ($data['Created_at & Updated_at'] == true) {
            $fields[] = '$table->timestamps()';
        }

        if ($data['soft_delete'] == true) {
            $fields[] = '$table->softDeletes()';
        }

        return implode(";\n", $fields);
    }

    private static function generateColumnDefinition(array $column): string
    {
        $definition = "\$table->{$column['type']}('{$column['name']}')";

        $methods = [
            'nullable' => fn (): bool => $column['nullable'] ?? false,
            'default' => fn (): ?string => $column['default'] ?? null,
            'comment' => fn (): ?string => $column['comment'] ?? null,
            'key' => fn (): ?string => $column['key'] ?? null,
        ];

        foreach ($methods as $method => $condition) {
            $value = $condition();
            if ($value !== null && $value !== false) {
                $definition .= match ($method) {
                    'nullable' => '->nullable()',
                    'default' => "->default('{$value}')",
                    'comment' => "->comment('{$value}')",
                    'key' => "->{$value}()",
                };
            }
        }

        return $definition;
    }

    public static function overwriteModelFile($filePath, $data)
    {
        $columns = self::getColumn($data);
        $modelName = self::getFileName($data['Model']);
        $tableName = $data['Table Name'] ?? '';
        
        $modelContent = "<?php\n\nnamespace App\Models;\n\n";
        
        // SoftDeletes trait if needed
        if ($data['soft_delete'] ?? false) {
            $modelContent .= "use Illuminate\Database\Eloquent\SoftDeletes;\n";
        }
        
        // Add FilamentShield trait use if needed
        $useShieldTrait = ($data['create_policy'] ?? false) && 
                        class_exists(\BezhanSalleh\FilamentShield\FilamentShield::class);
        
        if ($useShieldTrait) {
            $modelContent .= "use BezhanSalleh\FilamentShield\Traits\HasPanelShield;\n";
        }
        
        $modelContent .= "use Illuminate\Database\Eloquent\Factories\HasFactory;\n";
        $modelContent .= "use Illuminate\Database\Eloquent\Model;\n\n";
        $modelContent .= "class {$modelName} extends Model\n";
        $modelContent .= "{\n";
        $modelContent .= "    use HasFactory;\n";
        
        if ($data['soft_delete'] ?? false) {
            $modelContent .= "    use SoftDeletes;\n";
        }
        
        if ($useShieldTrait) {
            $modelContent .= "    use HasPanelShield;\n";
        }
        
        $modelContent .= "\n";
        
        // Add table name
        if ($tableName) {
            $modelContent .= "    protected \$table = '{$tableName}';\n";
        }
        
        // Add guard_name if using Shield
        if ($useShieldTrait) {
            $modelContent .= "    protected \$guard_name = 'web';\n";
        }
        
        $modelContent .= "    protected \$fillable = {$columns};\n";
        $modelContent .= "}\n";
        
        file_put_contents($filePath, $modelContent);
    }


    public static function getColumn($data)
    {
        $fields = [];
        foreach ($data['Table'] as $column) {
            $fields[] = "{$column['name']}";
        }

        return "['" . implode("','", $fields) . "']";
    }

    public static function overwriteControllerFile($filePath, $data)
    {
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            $changeIndex = <<<'EOD'
                public function index()
                    {
                        return 'This your index page';
                    }
                EOD;

            $content = preg_replace('/public function index.*?{.*?}/s', $changeIndex, $content);
            file_put_contents($filePath, $content);
        }

    }

    public static function addRoutes($data, $controllerName)
    {
        $filePath = base_path('routes\web.php');
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            $useStatement = <<<EOD
                use Illuminate\Support\Facades\Route;
                use App\Http\Controllers\\$controllerName;
                EOD;

            $addRoute = <<<EOD

                Route::resource('{$data['Table Name']}', {$controllerName}::class)->only([
                    'index', 'show'
                ]);
                EOD;

            $content = preg_replace('/use Illuminate\\\\Support\\\\Facades\\\\Route;/s', $useStatement, $content);
            $content .= $addRoute;

            file_put_contents($filePath, $content);
        }
    }

    public static function updatePolicyFile($filePath, $modelName) {

        // --- Check if FilamentShield is installed
        if (!class_exists(\BezhanSalleh\FilamentShield\FilamentShield::class)) {
            return;
        }
    
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            
            $modelFunctionNameVariable = Str::snake(Str::plural($modelName));
            $permissionBase = Str::of($modelName)
                ->afterLast('\\')
                ->snake()
                ->replace('_', '::');
            
            $methodTemplates = [
                'import_data' => "return \$user->can('import_data_{$permissionBase}');",
                'download_template_file' => "return \$user->can('download_template_file_{$permissionBase}');",
                'viewAny' => "return \$user->can('view_any_{$permissionBase}');",
                'view' => "return \$user->can('view_{$permissionBase}');",
                'create' => "return \$user->can('create_{$permissionBase}');",
                'update' => "return \$user->can('update_{$permissionBase}');",
                'delete' => "return \$user->can('delete_{$permissionBase}');",
                'deleteAny' => "return \$user->can('delete_any_{$permissionBase}');",
                'restore' => "return \$user->can('restore_{$permissionBase}');",
                'restoreAny' => "return \$user->can('restore_any_{$permissionBase}');",
                'forceDelete' => "return \$user->can('force_delete_{$permissionBase}');",
                'forceDeleteAny' => "return \$user->can('force_delete_any_{$permissionBase}');",
                'replicate' => "return \$user->can('replicate_{$permissionBase}');",
                'reorder' => "return \$user->can('reorder_{$permissionBase}');"
            ];
            
            $newMethods = '';
            foreach ($methodTemplates as $method => $returnStatement) {
                $methodSignature = "public function {$method}(User \$user" . 
                    (in_array($method, ['viewAny', 'create', 'deleteAny', 'restoreAny', 'forceDeleteAny', 'reorder', 'import_data', 'download_template_file']) 
                        ? "" 
                        : ", {$modelName} \${$modelFunctionNameVariable}"
                    ) . 
                    "): bool";
    
                $methodBody = "    {\n        {$returnStatement}\n    }";
    
                $fullMethod = "\n\n    {$methodSignature}\n{$methodBody}";
    
                // --- Check if the method already exists
                if (strpos($content, "public function {$method}(") === false) {
                    // Method doesn't exist, add it to newMethods
                    $newMethods .= $fullMethod;
                } else {
                    // --- Method exists, update it
                    $pattern = "/public function {$method}\([^\)]*\): bool\n\s*{\n.*?\n\s*}/s";
                    $replacement = "{$methodSignature}\n{$methodBody}";
                    $content = preg_replace($pattern, $replacement, $content);
                }
            }
            
            // --- Add new methods inside the class
            $content = preg_replace('/}(\s*)$/', $newMethods . "\n}", $content);
            
            file_put_contents($filePath, $content);
        }
    }


}
