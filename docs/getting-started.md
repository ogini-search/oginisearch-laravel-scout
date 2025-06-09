# Getting Started with OginiSearch Laravel Scout Driver

This guide will walk you through setting up and using the OginiSearch Laravel Scout Driver from installation to your first search implementation.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Configuration](#configuration)
- [Your First Search](#your-first-search)
- [Basic Model Setup](#basic-model-setup)
- [Testing Your Setup](#testing-your-setup)
- [Next Steps](#next-steps)

## Prerequisites

Before you begin, ensure you have:

- **PHP >= 8.2**
- **Laravel >= 12.0**
- **Laravel Scout >= 10.0**
- **OginiSearch Server** running (for production) or use our test environment

### Checking Your Environment

```bash
# Check PHP version
php --version

# Check Laravel version
php artisan --version

# Check if Scout is installed
composer show laravel/scout
```

## Installation

### Step 1: Install the Package

```bash
composer require ogini/oginisearch-laravel-scout
```

### Step 2: Install Laravel Scout (if not already installed)

```bash
composer require laravel/scout
```

### Step 3: Publish Configuration Files

```bash
# Publish Scout configuration
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"

# Publish OginiSearch configuration
php artisan vendor:publish --tag=ogini-config
```

### Step 4: Verify Installation

```bash
# Check if files were created
ls config/scout.php
ls config/ogini.php
```

## Configuration

### Step 1: Configure Laravel Scout

Edit `config/scout.php`:

```php
<?php

return [
    'driver' => env('SCOUT_DRIVER', 'ogini'),
    
    'prefix' => env('SCOUT_PREFIX', ''),
    
    'queue' => env('SCOUT_QUEUE', false),
    
    'after_commit' => false,
    
    'chunk' => [
        'searchable' => 500,
        'unsearchable' => 500,
    ],
    
    'soft_delete' => false,
    
    'identify' => env('SCOUT_IDENTIFY', true),
    
    'ogini' => [
        'model' => App\Models\SearchableModel::class,
    ],
];
```

### Step 2: Configure OginiSearch Connection

Edit your `.env` file:

```env
# Scout Configuration
SCOUT_DRIVER=ogini

# OginiSearch Configuration
OGINI_BASE_URL=http://localhost:3000
OGINI_API_KEY=your-api-key-here

# Optional Performance Settings
OGINI_TIMEOUT=30
OGINI_RETRY_ATTEMPTS=3
OGINI_BATCH_SIZE=100
OGINI_QUERY_CACHE_TTL=300
```

### Step 3: Configure OginiSearch Settings

Edit `config/ogini.php` for advanced configuration:

```php
<?php

return [
    'base_url' => env('OGINI_BASE_URL', 'http://localhost:3000'),
    'api_key' => env('OGINI_API_KEY'),
    
    'client' => [
        'timeout' => env('OGINI_TIMEOUT', 30),
        'retry_attempts' => env('OGINI_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('OGINI_RETRY_DELAY', 100),
    ],
    
    'engine' => [
        'max_results' => 1000,
        'default_limit' => 15,
    ],
    
    'performance' => [
        'cache' => [
            'enabled' => true,
            'driver' => 'redis', // or 'file', 'array'
            'query_ttl' => env('OGINI_QUERY_CACHE_TTL', 300),
        ],
        'batch_processing' => [
            'enabled' => true,
            'batch_size' => env('OGINI_BATCH_SIZE', 100),
        ],
    ],
];
```

## Your First Search

### Step 1: Create a Searchable Model

Let's create a blog post model as an example:

```bash
php artisan make:model BlogPost -m
```

### Step 2: Configure the Model

Edit `app/Models/BlogPost.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class BlogPost extends Model
{
    use Searchable;

    protected $fillable = [
        'title',
        'content',
        'author_name',
        'category',
        'published_at',
        'is_published',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_published' => 'boolean',
    ];

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
            'author_name' => $this->author_name,
            'category' => $this->category,
            'published_at' => $this->published_at?->toISOString(),
            'is_published' => $this->is_published,
        ];
    }

    /**
     * Get the index name for the model.
     */
    public function searchableAs(): string
    {
        return 'blog_posts';
    }

    /**
     * Determine if the model should be searchable.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->is_published;
    }
}
```

### Step 3: Create the Migration

Edit the migration file (`database/migrations/xxxx_create_blog_posts_table.php`):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->string('author_name');
            $table->string('category');
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
```

### Step 4: Run the Migration

```bash
php artisan migrate
```

## Basic Model Setup

### Creating Test Data

Create a seeder to add some test data:

```bash
php artisan make:seeder BlogPostSeeder
```

Edit `database/seeders/BlogPostSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use Illuminate\Database\Seeder;

class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        $posts = [
            [
                'title' => 'Getting Started with Laravel',
                'content' => 'Laravel is a powerful PHP framework that makes web development enjoyable and efficient.',
                'author_name' => 'John Doe',
                'category' => 'Programming',
                'published_at' => now(),
                'is_published' => true,
            ],
            [
                'title' => 'Advanced Search with OginiSearch',
                'content' => 'Learn how to implement advanced search functionality in your Laravel applications.',
                'author_name' => 'Jane Smith',
                'category' => 'Tutorial',
                'published_at' => now(),
                'is_published' => true,
            ],
            [
                'title' => 'PHP Best Practices',
                'content' => 'Discover the best practices for writing clean and maintainable PHP code.',
                'author_name' => 'Bob Johnson',
                'category' => 'Programming',
                'published_at' => now(),
                'is_published' => true,
            ],
            [
                'title' => 'Draft Post',
                'content' => 'This is a draft post that should not appear in search results.',
                'author_name' => 'Alice Brown',
                'category' => 'Draft',
                'published_at' => null,
                'is_published' => false,
            ],
        ];

        foreach ($posts as $post) {
            BlogPost::create($post);
        }
    }
}
```

Run the seeder:

```bash
php artisan db:seed --class=BlogPostSeeder
```

### Indexing Your Data

Now let's index the blog posts:

```bash
# Import all existing blog posts
php artisan scout:import "App\Models\BlogPost"
```

## Testing Your Setup

### Step 1: Create a Test Route

Add to `routes/web.php`:

```php
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/search', function (Request $request) {
    $query = $request->get('q', '');
    
    if (empty($query)) {
        return view('search', ['posts' => collect(), 'query' => '']);
    }
    
    $posts = BlogPost::search($query)->get();
    
    return view('search', compact('posts', 'query'));
});
```

### Step 2: Create a Search View

Create `resources/views/search.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <title>Blog Search</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .search-form { margin-bottom: 30px; }
        .search-input { width: 70%; padding: 10px; font-size: 16px; }
        .search-button { padding: 10px 20px; font-size: 16px; }
        .post { border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        .post-title { font-size: 20px; font-weight: bold; color: #333; }
        .post-meta { color: #666; margin: 10px 0; }
        .post-content { line-height: 1.6; }
        .no-results { text-align: center; color: #666; margin: 40px 0; }
    </style>
</head>
<body>
    <h1>Blog Search</h1>
    
    <form class="search-form" method="GET">
        <input 
            type="text" 
            name="q" 
            value="{{ $query }}" 
            placeholder="Search blog posts..." 
            class="search-input"
        >
        <button type="submit" class="search-button">Search</button>
    </form>
    
    @if(!empty($query))
        <p>Search results for: <strong>{{ $query }}</strong></p>
        
        @if($posts->count() > 0)
            @foreach($posts as $post)
                <div class="post">
                    <div class="post-title">{{ $post->title }}</div>
                    <div class="post-meta">
                        By {{ $post->author_name }} in {{ $post->category }} 
                        • {{ $post->published_at->format('M j, Y') }}
                    </div>
                    <div class="post-content">{{ Str::limit($post->content, 200) }}</div>
                </div>
            @endforeach
        @else
            <div class="no-results">No posts found matching your search.</div>
        @endif
    @endif
</body>
</html>
```

### Step 3: Test Your Search

1. Start your Laravel development server:
   ```bash
   php artisan serve
   ```

2. Visit `http://localhost:8000/search`

3. Try searching for:
   - "Laravel" (should find the Laravel post)
   - "PHP" (should find the PHP best practices post)
   - "search" (should find the OginiSearch post)
   - "draft" (should find nothing since drafts aren't indexed)

### Step 4: Verify Search Functionality

Test in your browser or using a simple PHP script:

```php
// Quick test script - save as test-search.php
<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BlogPost;

// Test basic search
echo "Testing basic search...\n";
$results = BlogPost::search('Laravel')->get();
echo "Found " . $results->count() . " results for 'Laravel'\n\n";

foreach ($results as $post) {
    echo "Title: " . $post->title . "\n";
    echo "Author: " . $post->author_name . "\n";
    echo "Category: " . $post->category . "\n";
    echo "---\n";
}

// Test with options
echo "\nTesting search with filters...\n";
$results = BlogPost::search('programming')
    ->options([
        'filter' => ['category' => 'Programming']
    ])
    ->get();

echo "Found " . $results->count() . " results for 'programming' in Programming category\n";
```

Run the test:

```bash
php test-search.php
```

## Next Steps

Congratulations! You now have a working OginiSearch setup. Here's what to explore next:

### 1. Advanced Search Features
- [Advanced Search Guide](./advanced-search-tutorial.md) - Complex queries, filtering, sorting
- [Performance Optimization](./performance-optimization.md) - Caching, indexing strategies

### 2. Production Deployment
- [Production Configuration](./production-configuration.md) - Environment setup, security
- [Monitoring & Logging](./monitoring.md) - Performance tracking, debugging

### 3. API Reference
- [Complete API Documentation](./api-reference.md) - All available methods and options
- [Configuration Reference](./configuration-reference.md) - All configuration options

### 4. Examples & Tutorials
- [Search Examples](../examples/) - Real-world search implementations
- [Custom Configuration Tutorial](./custom-configuration-tutorial.md) - Advanced setup options

## Troubleshooting

### Common Issues

#### Connection Errors
```bash
# Check if OginiSearch server is running
curl http://localhost:3000/health

# Check Laravel configuration
php artisan config:cache
php artisan config:clear
```

#### Indexing Issues
```bash
# Re-import all models
php artisan scout:import "App\Models\BlogPost"

# Flush and re-import
php artisan scout:flush "App\Models\BlogPost"
php artisan scout:import "App\Models\BlogPost"
```

#### Search Not Working
```php
// Debug search query
use OginiScoutDriver\Facades\Ogini;

$results = Ogini::search('blog_posts', [
    'query' => ['match' => ['title' => 'Laravel']],
    'size' => 10
]);

dd($results); // Check raw results
```

### Getting Help

- **Documentation**: Read our [complete documentation](./api-reference.md)
- **Examples**: Check the [examples directory](../examples/)
- **Issues**: Report bugs on [GitHub Issues](https://github.com/ogini-search/oginisearch-laravel-scout/issues)
- **Community**: Join our [Discord community](https://discord.gg/ogini)

### Performance Tips

1. **Use Caching**: Enable query caching in production
2. **Batch Indexing**: Use `scout:import` for bulk operations
3. **Optimize Queries**: Use filters and specific field searches
4. **Monitor Performance**: Check our [benchmarks](./benchmark-results.md)

---

**Ready to dive deeper?** Continue with our [Basic Search Tutorial](./basic-search-tutorial.md) to learn more search techniques. 