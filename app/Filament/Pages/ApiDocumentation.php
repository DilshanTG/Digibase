<?php

namespace App\Filament\Pages;

use App\Models\DynamicModel;
use App\Services\ApiDocumentationService;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use BackedEnum;
use UnitEnum;

class ApiDocumentation extends Page
{
    protected string $view = 'filament.pages.api-documentation';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';
    protected static string|UnitEnum|null $navigationGroup = 'Developer';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'API Documentation';

    public ?int $selectedModelId = null;
    public ?array $documentation = null;
    
    // Try It Out state
    public string $testApiKey = '';
    public string $testRequestBody = '{}';
    public ?array $testResponse = null;
    public ?int $testStatusCode = null;
    public bool $testLoading = false;

    public function mount(): void
    {
        $this->selectedModelId = request()->query('model');
        
        if ($this->selectedModelId) {
            $this->loadDocumentation();
        }
    }

    public function schema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('selectedModelId')
                    ->label('Select Table')
                    ->options(
                        DynamicModel::where('user_id', auth()->id())
                            ->where('is_active', true)
                            ->pluck('display_name', 'id')
                    )
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn () => $this->loadDocumentation()),
            ]);
    }

    public function loadDocumentation(): void
    {
        if (!$this->selectedModelId) {
            $this->documentation = null;
            return;
        }

        $model = DynamicModel::find($this->selectedModelId);
        
        if (!$model || $model->user_id !== auth()->id()) {
            $this->documentation = null;
            return;
        }

        $service = new ApiDocumentationService();
        $this->documentation = $service->generateDocumentation($model);
        
        // Reset test state
        $this->testResponse = null;
        $this->testStatusCode = null;
        $this->testRequestBody = json_encode($this->documentation['examples']['javascript']['create'] ?? [], JSON_PRETTY_PRINT);
    }
    
    public function downloadOpenApiSpec(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $model = DynamicModel::find($this->selectedModelId);
        
        if (!$model || $model->user_id !== auth()->id()) {
            abort(403);
        }
        
        $service = new ApiDocumentationService();
        $spec = $service->generateOpenApiSpec($model);
        
        $filename = 'openapi-' . $model->table_name . '.json';
        
        return response()->streamDownload(function () use ($spec) {
            echo json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }
    
    public function testEndpoint(string $method, string $endpoint): void
    {
        $this->testLoading = true;
        $this->testResponse = null;
        $this->testStatusCode = null;
        
        try {
            $model = DynamicModel::find($this->selectedModelId);
            
            if (!$model || $model->user_id !== auth()->id()) {
                $this->testResponse = ['error' => 'Unauthorized'];
                $this->testStatusCode = 403;
                return;
            }
            
            $url = config('app.url') . $endpoint;
            
            $request = \Illuminate\Support\Facades\Http::withHeaders([
                'x-api-key' => $this->testApiKey,
                'Accept' => 'application/json',
            ]);
            
            if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
                $body = json_decode($this->testRequestBody, true);
                $response = $request->$method($url, $body);
            } else {
                $response = $request->$method($url);
            }
            
            $this->testStatusCode = $response->status();
            $this->testResponse = $response->json();
            
        } catch (\Exception $e) {
            $this->testResponse = ['error' => $e->getMessage()];
            $this->testStatusCode = 500;
        } finally {
            $this->testLoading = false;
        }
    }
}
