<?php

namespace OginiScoutDriver\Tests\Integration\Factories;

use OginiScoutDriver\Tests\Integration\Models\TestProduct;
use OginiScoutDriver\Tests\Integration\Models\TestUser;
use OginiScoutDriver\Tests\Integration\Models\TestArticle;
use Carbon\Carbon;

class TestDataFactory
{
    /**
     * Create test products.
     *
     * @param int $count
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function createProducts(int $count = 10): \Illuminate\Database\Eloquent\Collection
    {
        $products = (new TestProduct())->newCollection();

        $categories = ['Electronics', 'Books', 'Clothing', 'Home & Garden', 'Sports'];
        $statuses = ['published', 'draft', 'archived'];
        $tags = ['sale', 'featured', 'new', 'trending', 'bestseller'];

        for ($i = 1; $i <= $count; $i++) {
            $product = TestProduct::create([
                'title' => "Test Product {$i}",
                'description' => "This is a detailed description for test product {$i}. It includes various features and specifications that make this product unique and valuable for customers.",
                'price' => mt_rand(1000, 50000) / 100, // $10.00 - $500.00
                'category' => $categories[array_rand($categories)],
                'status' => $statuses[array_rand($statuses)],
                'is_featured' => mt_rand(0, 1) === 1,
                'tags' => array_slice($tags, 0, mt_rand(1, 3)),
                'created_at' => Carbon::now()->subDays(mt_rand(0, 30)),
                'updated_at' => Carbon::now()->subDays(mt_rand(0, 5)),
            ]);

            $products->push($product);
        }

        return $products;
    }

    /**
     * Create test users.
     *
     * @param int $count
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function createUsers(int $count = 5): \Illuminate\Database\Eloquent\Collection
    {
        $users = (new TestUser())->newCollection();

        $roles = ['admin', 'editor', 'author', 'subscriber'];
        $names = ['John Doe', 'Jane Smith', 'Bob Johnson', 'Alice Brown', 'Charlie Wilson'];

        for ($i = 1; $i <= $count; $i++) {
            $name = $names[($i - 1) % count($names)];
            $user = TestUser::create([
                'name' => $name,
                'email' => strtolower(str_replace(' ', '.', $name)) . "+{$i}@example.com",
                'role' => $roles[array_rand($roles)],
                'active' => mt_rand(0, 1) === 1,
                'created_at' => Carbon::now()->subDays(mt_rand(0, 60)),
                'updated_at' => Carbon::now()->subDays(mt_rand(0, 10)),
            ]);

            $users->push($user);
        }

        return $users;
    }

    /**
     * Create test articles.
     *
     * @param int $count
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function createArticles(int $count = 8): \Illuminate\Database\Eloquent\Collection
    {
        $articles = (new TestArticle())->newCollection();

        $authors = ['John Smith', 'Sarah Johnson', 'Mike Davis', 'Emma Wilson'];
        $statuses = ['published', 'draft', 'review'];
        $titles = [
            'Introduction to Modern Web Development',
            'Best Practices for Database Design',
            'Understanding Machine Learning Algorithms',
            'The Future of Artificial Intelligence',
            'Building Scalable Microservices',
            'Cloud Computing Security Guidelines',
            'Mobile App Development Trends',
            'DevOps Automation Strategies',
        ];

        for ($i = 1; $i <= $count; $i++) {
            $isPublished = $statuses[array_rand($statuses)] === 'published';

            $article = TestArticle::create([
                'title' => $titles[($i - 1) % count($titles)],
                'content' => "This is the content for article {$i}. It contains detailed information about the topic, providing valuable insights and practical examples for readers. The article covers various aspects and includes real-world scenarios to help readers understand the concepts better.",
                'author' => $authors[array_rand($authors)],
                'status' => $statuses[array_rand($statuses)],
                'published_at' => $isPublished ? Carbon::now()->subDays(mt_rand(1, 30)) : null,
                'views' => mt_rand(0, 1000),
                'created_at' => Carbon::now()->subDays(mt_rand(0, 45)),
                'updated_at' => Carbon::now()->subDays(mt_rand(0, 7)),
            ]);

            $articles->push($article);
        }

        return $articles;
    }

    /**
     * Create a specific product for testing.
     *
     * @param array $attributes
     * @return TestProduct
     */
    public static function createSpecificProduct(array $attributes = []): TestProduct
    {
        $defaults = [
            'title' => 'Specific Test Product',
            'description' => 'This is a specific product created for testing purposes.',
            'price' => 99.99,
            'category' => 'Electronics',
            'status' => 'published',
            'is_featured' => true,
            'tags' => ['test', 'specific'],
        ];

        return TestProduct::create(array_merge($defaults, $attributes));
    }

    /**
     * Create a specific user for testing.
     *
     * @param array $attributes
     * @return TestUser
     */
    public static function createSpecificUser(array $attributes = []): TestUser
    {
        $defaults = [
            'name' => 'Test User',
            'email' => 'test.user@example.com',
            'role' => 'user',
            'active' => true,
        ];

        return TestUser::create(array_merge($defaults, $attributes));
    }

    /**
     * Create a specific article for testing.
     *
     * @param array $attributes
     * @return TestArticle
     */
    public static function createSpecificArticle(array $attributes = []): TestArticle
    {
        $defaults = [
            'title' => 'Specific Test Article',
            'content' => 'This is a specific article created for testing purposes.',
            'author' => 'Test Author',
            'status' => 'published',
            'published_at' => Carbon::now()->subDay(),
            'views' => 0,
        ];

        return TestArticle::create(array_merge($defaults, $attributes));
    }

    /**
     * Clean up all test data.
     *
     * @return void
     */
    public static function cleanup(): void
    {
        TestProduct::truncate();
        TestUser::truncate();
        TestArticle::truncate();
    }
}
