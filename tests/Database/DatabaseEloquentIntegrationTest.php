<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model as Eloquent;

class DatabaseEloquentIntegrationTest extends PHPUnit_Framework_TestCase {

	/**
	 * Bootstrap Eloquent.
	 *
	 * @return void
	 */
	public static function setUpBeforeClass()
	{
		Eloquent::setConnectionResolver(
			new DatabaseIntegrationTestConnectionResolver
		);

		Eloquent::setEventDispatcher(
			new Illuminate\Events\Dispatcher
		);
	}


	/**
	 * Tear down Eloquent.
	 */
	public static function tearDownAfterClass()
	{
		Eloquent::unsetEventDispatcher();
		Eloquent::unsetConnectionResolver();
	}


	/**
	 * Setup the database schema.
	 *
	 * @return void
	 */
	public function setUp()
	{
		$this->schema()->create('users', function($table) {
			$table->increments('id');
			$table->string('email')->unique();
			$table->timestamps();
		});

		$this->schema()->create('friends', function($table) {
			$table->integer('user_id');
			$table->integer('friend_id');
		});

		$this->schema()->create('posts', function($table) {
			$table->increments('id');
			$table->integer('user_id');
			$table->string('name');
			$table->timestamps();
		});

		$this->schema()->create('photos', function($table) {
			$table->increments('id');
			$table->morphs('imageable');
			$table->string('name');
			$table->timestamps();
		});
	}


	/**
	 * Tear down the database schema.
	 *
	 * @return void
	 */
	public function tearDown()
	{
		$this->schema()->drop('users');
		$this->schema()->drop('friends');
		$this->schema()->drop('posts');
		$this->schema()->drop('photos');
	}

	/**
	 * Tests...
	 */
	public function testBasicModelRetrieval()
	{
		EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
		$model = EloquentTestUser::where('email', 'taylorotwell@gmail.com')->first();
		$this->assertEquals('taylorotwell@gmail.com', $model->email);
	}


	public function testBasicModelCollectionRetrieval()
	{
		EloquentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
		EloquentTestUser::create(['id' => 2, 'email' => 'abigailotwell@gmail.com']);

		$models = EloquentTestUser::oldest('id')->get();

		$this->assertEquals(2, $models->count());
		$this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $models);
		$this->assertInstanceOf('EloquentTestUser', $models[0]);
		$this->assertInstanceOf('EloquentTestUser', $models[1]);
		$this->assertEquals('taylorotwell@gmail.com', $models[0]->email);
		$this->assertEquals('abigailotwell@gmail.com', $models[1]->email);
	}


	public function testListsRetrieval()
	{
		EloquentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
		EloquentTestUser::create(['id' => 2, 'email' => 'abigailotwell@gmail.com']);

		$simple = EloquentTestUser::oldest('id')->lists('users.email');
		$keyed = EloquentTestUser::oldest('id')->lists('users.email', 'users.id');

		$this->assertEquals(['taylorotwell@gmail.com', 'abigailotwell@gmail.com'], $simple);
		$this->assertEquals([1 => 'taylorotwell@gmail.com', 2 => 'abigailotwell@gmail.com'], $keyed);
	}


	public function testFindOrFail()
	{
		EloquentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
		EloquentTestUser::create(['id' => 2, 'email' => 'abigailotwell@gmail.com']);

		$single = EloquentTestUser::findOrFail(1);
		$multiple = EloquentTestUser::findOrFail([1, 2]);

		$this->assertInstanceOf('EloquentTestUser', $single);
		$this->assertEquals('taylorotwell@gmail.com', $single->email);
		$this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $multiple);
		$this->assertInstanceOf('EloquentTestUser', $multiple[0]);
		$this->assertInstanceOf('EloquentTestUser', $multiple[1]);
	}

	/**
	 * @expectedException Illuminate\Database\Eloquent\ModelNotFoundException
	 */
	public function testFindOrFailWithSingleIdThrowsModelNotFoundException()
	{
		EloquentTestUser::findOrFail(1);
	}

	/**
	 * @expectedException Illuminate\Database\Eloquent\ModelNotFoundException
	 */
	public function testFindOrFailWithMultipleIdsThrowsModelNotFoundException()
	{
		EloquentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
		EloquentTestUser::findOrFail([1, 2]);
	}


	public function testOneToOneRelationship()
	{
		$user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
		$user->post()->create(['name' => 'First Post']);

		$post = $user->post;
		$user = $post->user;

		$this->assertInstanceOf('EloquentTestUser', $user);
		$this->assertInstanceOf('EloquentTestPost', $post);
		$this->assertEquals('taylorotwell@gmail.com', $user->email);
		$this->assertEquals('First Post', $post->name);
	}


	public function testOneToManyRelationship()
	{
		$user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
		$user->posts()->create(['name' => 'First Post']);
		$user->posts()->create(['name' => 'Second Post']);

		$posts = $user->posts;
		$post2 = $user->posts()->where('name', 'Second Post')->first();

		$this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $posts);
		$this->assertEquals(2, $posts->count());
		$this->assertInstanceOf('EloquentTestPost', $posts[0]);
		$this->assertInstanceOf('EloquentTestPost', $posts[1]);
		$this->assertInstanceOf('EloquentTestPost', $post2);
		$this->assertEquals('Second Post', $post2->name);
		$this->assertInstanceOf('EloquentTestUser', $post2->user);
		$this->assertEquals('taylorotwell@gmail.com', $post2->user->email);
	}


	public function testHasOnSelfReferencingBelongsToManyRelationship()
	{
		$user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
		$friend = $user->friends()->create(['email' => 'abigailotwell@gmail.com']);

		$results = EloquentTestUser::has('friends')->get();

		$this->assertEquals(1, count($results));
		$this->assertEquals('taylorotwell@gmail.com', $results->first()->email);
	}


	public function testBasicHasManyEagerLoading()
	{
		$user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
		$user->posts()->create(['name' => 'First Post']);
		$user = EloquentTestUser::with('posts')->where('email', 'taylorotwell@gmail.com')->first();

		$this->assertEquals('First Post', $user->posts->first()->name);

		$post = EloquentTestPost::with('user')->where('name', 'First Post')->get();
		$this->assertEquals('taylorotwell@gmail.com', $post->first()->user->email);
	}


	public function testBasicMorphManyRelationship()
	{
		$user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
		$user->photos()->create(['name' => 'Avatar 1']);
		$user->photos()->create(['name' => 'Avatar 2']);
		$post = $user->posts()->create(['name' => 'First Post']);
		$post->photos()->create(['name' => 'Hero 1']);
		$post->photos()->create(['name' => 'Hero 2']);

		$this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->photos);
		$this->assertInstanceOf('EloquentTestPhoto', $user->photos[0]);
		$this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $post->photos);
		$this->assertInstanceOf('EloquentTestPhoto', $post->photos[0]);
		$this->assertEquals(2, $user->photos->count());
		$this->assertEquals(2, $post->photos->count());
		$this->assertEquals('Avatar 1', $user->photos[0]->name);
		$this->assertEquals('Avatar 2', $user->photos[1]->name);
		$this->assertEquals('Hero 1', $post->photos[0]->name);
		$this->assertEquals('Hero 2', $post->photos[1]->name);

		$photos = EloquentTestPhoto::orderBy('name')->get();

		$this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $photos);
		$this->assertEquals(4, $photos->count());
		$this->assertInstanceOf('EloquentTestUser', $photos[0]->imageable);
		$this->assertInstanceOf('EloquentTestPost', $photos[2]->imageable);
		$this->assertEquals('taylorotwell@gmail.com', $photos[1]->imageable->email);
		$this->assertEquals('First Post', $photos[3]->imageable->name);
	}


	public function testMultiInsertsWithDifferentValues()
	{
		$date = '1970-01-01';
		$result = EloquentTestPost::insert([
			['user_id' => 1, 'name' => 'Post', 'created_at' => $date, 'updated_at' => $date],
			['user_id' => 2, 'name' => 'Post', 'created_at' => $date, 'updated_at' => $date],
		]);

		$this->assertTrue($result);
		$this->assertEquals(2, EloquentTestPost::count());
	}


	public function testMultiInsertsWithSameValues()
	{
		$date = '1970-01-01';
		$result = EloquentTestPost::insert([
			['user_id' => 1, 'name' => 'Post', 'created_at' => $date, 'updated_at' => $date],
			['user_id' => 1, 'name' => 'Post', 'created_at' => $date, 'updated_at' => $date],
		]);

		$this->assertTrue($result);
		$this->assertEquals(2, EloquentTestPost::count());
	}

	/**
	 * Helpers...
	 */

	/**
	 * Get a database connection instance.
	 *
	 * @return Connection
	 */
	protected function connection()
	{
		return Eloquent::getConnectionResolver()->connection();
	}

	/**
	 * Get a schema builder instance.
	 *
	 * @return Schema\Builder
	 */
	protected function schema()
	{
		return $this->connection()->getSchemaBuilder();
	}

}

/**
 * Eloquent Models...
 */

class EloquentTestUser extends Eloquent {
	protected $table = 'users';
	protected $guarded = [];
	public function friends() {
		return $this->belongsToMany('EloquentTestUser', 'friends', 'user_id', 'friend_id');
	}
	public function posts() {
		return $this->hasMany('EloquentTestPost', 'user_id');
	}
	public function post() {
		return $this->hasOne('EloquentTestPost', 'user_id');
	}
	public function photos() {
		return $this->morphMany('EloquentTestPhoto', 'imageable');
	}
}

class EloquentTestPost extends Eloquent {
	protected $table = 'posts';
	protected $guarded = [];
	public function user() {
		return $this->belongsTo('EloquentTestUser', 'user_id');
	}
	public function photos() {
		return $this->morphMany('EloquentTestPhoto', 'imageable');
	}
}

class EloquentTestPhoto extends Eloquent {
	protected $table = 'photos';
	protected $guarded = [];
	public function imageable(){
		return $this->morphTo();
	}
}

/**
 * Connection Resolver
 */

class DatabaseIntegrationTestConnectionResolver implements Illuminate\Database\ConnectionResolverInterface {

	protected $connection;

	public function connection($name = null)
	{
		if (isset($this->connection)) return $this->connection;
		return $this->connection = new Illuminate\Database\SQLiteConnection(new PDO('sqlite::memory:'));
	}
	public function getDefaultConnection()
	{
		return 'default';
	}
	public function setDefaultConnection($name)
	{
		//
	}
}
