<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use BackedEnum;
use UnitEnum;

class SqlPlayground extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-command-line';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'SQL Playground';

    protected static ?string $title = 'SQL Playground';

    protected string $view = 'filament.pages.sql-playground';

    public ?string $query = '';
    public ?array $results = [];
    public ?string $message = '';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Run SQL Commands')
                    ->description('Execute raw SQL (CREATE, DROP, SELECT). Use with Caution.')
                    ->schema([
                        Textarea::make('query')
                            ->label('SQL Query')
                            ->placeholder("SELECT * FROM users LIMIT 5;\n-- or --\nCREATE TABLE logs (id int);")
                            ->rows(10)
                            ->required(),
                    ])
                    ->footerActions([
                        Action::make('execute')
                            ->label('Run Query')
                            ->icon('heroicon-o-play')
                            ->color('danger')
                            ->action(fn () => $this->runQuery()),
                    ]),
            ]);
    }

    public function runQuery()
    {
        $this->results = [];
        $this->message = '';
        
        try {
            $sql = trim($this->query);
            if (empty($sql)) return;

            if (stripos($sql, 'SELECT') === 0 || stripos($sql, 'SHOW') === 0 || stripos($sql, 'DESCRIBE') === 0) {
                // Wrap in json mapping to ensure $row is an array/object for the Blade table loop
                $this->results = json_decode(json_encode(DB::select($sql)), true);
                
                Notification::make()
                    ->success()
                    ->title('Query Loaded')
                    ->body(count($this->results) . ' rows returned.')
                    ->send();
            } else {
                DB::statement($sql);
                $this->message = "Executed successfully.";
                Notification::make()
                    ->success()
                    ->title('Command Executed')
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('SQL Error')
                ->body($e->getMessage())
                ->persistent()
                ->send();
        }
    }
}
