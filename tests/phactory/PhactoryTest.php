<?php
require_once 'PHPUnit/Framework.php';

require_once PHACTORY_PATH . '/Phactory.php';

/**
 * Test class for Phactory.
 * Generated by PHPUnit on 2010-06-28 at 09:16:34.
 */
class PhactoryTest extends PHPUnit_Framework_TestCase
{
    protected $pdo;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->pdo = new PDO("sqlite:test.db");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("CREATE TABLE `users` ( id INTEGER PRIMARY KEY, name TEXT, role_id INTEGER )");
        $this->pdo->exec("CREATE TABLE `roles` ( id INTEGER PRIMARY KEY, name TEXT )");

        Phactory::setConnection($this->pdo);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
        Phactory::reset();

        $this->pdo->exec("DROP TABLE `users`");
        $this->pdo->exec("DROP TABLE `roles`");
    }

    public function testSetConnection()
    {
        $pdo = new PDO("sqlite:test.db");
        Phactory::setConnection($pdo);
        $pdo = Phactory::getConnection();
        $this->assertType('PDO', $pdo);
    }

    public function testGetConnection()
    {
        $pdo = Phactory::getConnection();
        $this->assertType('PDO', $pdo);
    }

    public function testDefine()
    {
        // test that define() doesn't throw an exception when called correctly
        Phactory::define('user', array('name' => 'testuser'));
    }


    public function testDefineWithBlueprint()
    {
        $blueprint = new Phactory_Blueprint('user', array('name' => 'testuser'));
        Phactory::define('user', $blueprint);

        $user = Phactory::create('user');
        $this->assertEquals('testuser', $user->name);
    }

    public function testDefineWithAssociations()
    {
        // define with explicit $to_column
        Phactory::define('user',
                         array('name' => 'testuser'),
                         array('role' => Phactory::manyToOne('roles', 'role_id', 'id')));

        // definie with implicit $to_column
        Phactory::define('user',
                         array('name' => 'testuser'),
                         array('role' => Phactory::manyToOne('roles', 'role_id')));
    }

    public function testCreate()
    {
        $name = 'testuser';

        // define and create user in db
        Phactory::define('user', array('name' => $name));
        $user = Phactory::create('user');

        // test returned Phactory_Row
        $this->assertType('Phactory_Row', $user);
        $this->assertEquals($user->name, $name);

        // retrieve expected row from database
        $stmt = $this->pdo->query("SELECT * FROM `users`");
        $db_user = $stmt->fetch();

        // test retrieved db row
        $this->assertEquals($db_user['name'], $name);
    }

    public function testCreateWithOverrides()
    {
        $name = 'testuser';
        $override_name = 'override_user';

        // define and create user in db
        Phactory::define('user', array('name' => $name));
        $user = Phactory::create('user', array('name' => $override_name));

        // test returned Phactory_Row
        $this->assertType('Phactory_Row', $user);
        $this->assertEquals($user->name, $override_name);

        // retrieve expected row from database
        $stmt = $this->pdo->query("SELECT * FROM `users`");
        $db_user = $stmt->fetch();

        // test retrieved db row
        $this->assertEquals($db_user['name'], $override_name);
    }

    public function testCreateWithAssociations()
    {
        Phactory::define('role',
                         array('name' => 'admin'));
        Phactory::define('user',
                         array('name' => 'testuser'),
                         array('role' => Phactory::manyToOne('roles', 'role_id')));

        $role = Phactory::create('role'); 
        $user = Phactory::createWithAssociations('user', array('role' => $role));

        $this->assertNotNull($role->id);
        $this->assertEquals($role->id, $user->role_id);
    }

    public function testCreateWithManyToManyAssociation() {
        $this->pdo->exec("CREATE TABLE blogs ( id INTEGER PRIMARY KEY, title TEXT )");
        $this->pdo->exec("CREATE TABLE tags ( id INTEGER PRIMARY KEY, name TEXT )");
        $this->pdo->exec("CREATE TABLE blogs_tags ( blog_id INTEGER, tag_id INTEGER )");

        Phactory::define('tag',
                         array('name' => 'Test Tag'));
        Phactory::define('blog',
                         array('title' => 'Test Title'),
                         array('tag' => Phactory::manyToMany('tags', 'blogs_tags', 'id', 'blog_id', 'tag_id', 'id')));

        $tag = Phactory::create('tag');
        $blog = Phactory::createWithAssociations('blog', array('tag' => $tag));

        $result = $this->pdo->query("SELECT * FROM blogs_tags");
        $row = $result->fetch();
        $result->closeCursor();

        $this->assertNotEquals(false, $row);
        $this->assertEquals($blog->getId(), $row['blog_id']);
        $this->assertEquals($tag->getId(), $row['tag_id']);

        $this->pdo->exec("DROP TABLE blogs");
        $this->pdo->exec("DROP TABLE tags");
        $this->pdo->exec("DROP TABLE blogs_tags");
    }

    public function testDefineAndCreateWithSequence()
    {
        Phactory::define('user', array('name' => 'user\$n'));

        for($i = 0; $i < 5; $i++) {
            $user = Phactory::create('user');
            $this->assertEquals("user$i", $user->name);
        }
    }

    public function testGet()
    {
        $name = 'testuser';

        // define and create user in db
        Phactory::define('user', array('name' => $name));
        $user = Phactory::create('user');

        // get() expected row from database
        $db_user = Phactory::get('user', array('name' => $name)); 

        // test retrieved db row
        $this->assertEquals($db_user->name, $name);
        $this->assertType('Phactory_Row', $db_user);
    }

    public function testRecall()
    {
        $name = 'testuser';

        // define and create user in db
        Phactory::define('user', array('name' => $name));
        $user = Phactory::create('user');

        // recall() deletes from the db
        Phactory::recall();

        // test that the object is gone from the db
        $stmt = $this->pdo->query("SELECT * FROM `users`");
        $db_user = $stmt->fetch();
        $this->assertFalse($db_user);

        // test that the blueprints weren't destroyed too
        $user = Phactory::create('user');
        $this->assertEquals($user->name, $name);
    }
}
?>
