<?php

namespace Queryflatfile\Test;

use Queryflatfile\Request;
use Queryflatfile\Schema;
use Queryflatfile\TableBuilder;

class RequestTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Schema
     */
    protected $bdd;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Request
     */
    protected $request2;

    /**
     * @var string
     */
    protected static $root = '';

    public static function tearDownAfterClass()
    {
        if (!file_exists(self::$root)) {
            return;
        }
        $dir = new \DirectoryIterator(self::$root);
        foreach ($dir as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }
            unlink($fileInfo->getRealPath());
        }
        if (file_exists(self::$root)) {
            rmdir(self::$root);
        }
    }

    protected function setUp()
    {
        self::$root = __DIR__ . '/data/';
        $this->bdd  = (new Schema)
            ->setConfig('data', 'schema', new \Queryflatfile\Driver\Json())
            ->setPathRoot(__DIR__ . '/');

        $this->request  = new Request($this->bdd);
        $this->request2 = new Request($this->bdd);
    }

    public function testCreateTable()
    {
        $this->bdd->createTable('user', static function (TableBuilder $table) {
            $table->increments('id')
                ->string('name')->nullable()
                ->string('firstname')->nullable();
        });
        $this->bdd->createTable('user_role', static function (TableBuilder $table) {
            $table->integer('id_user')
                ->integer('id_role');
        });
        $this->bdd->createTable('role', static function (TableBuilder $table) {
            $table->increments('id_role')
                ->string('labelle');
        });

        self::assertFileExists(self::$root . 'user.' . $this->bdd->getExtension());
        self::assertFileExists(self::$root . 'user_role.' . $this->bdd->getExtension());
        self::assertFileExists(self::$root . 'role.' . $this->bdd->getExtension());
    }

    /**
     * @expectedException \Exception
     */
    public function testCreateTableException()
    {
        $this->bdd->createTable('user');
    }

    public function testCreateTableIfNotExists()
    {
        $this->bdd->createTableIfNotExists('user');

        self::assertFileExists(self::$root . 'user.' . $this->bdd->getExtension());
    }

    public function testInsertInto()
    {
        $this->request->insertInto('user', [ 'id', 'name', 'firstname' ])
            ->values([ 0, 'NOEL', 'Mathieu' ])
            ->values([ 1, 'DUPOND', 'Jean' ])
            ->execute();

        $this->request->insertInto('user', [ 'name', 'firstname' ])
            ->values([ 'MARTIN', 'Manon' ])
            ->values([ null, 'Marie' ])
            ->values([ 'DUPOND', 'Pierre' ])
            ->execute();

        $this->request->insertInto('user', [ 'id', 'name', 'firstname' ])
            ->values([ 5, 'MEYER', 'Eva' ])
            ->values([ 6, 'ROBERT', null ])
            ->execute();

        $this->request->insertInto('role', [ 'id_role', 'labelle' ])
            ->values([ 0, 'Admin' ])
            ->values([ 1, 'Author' ])
            ->values([ 2, 'User' ])
            ->execute();

        $this->request->insertInto('user_role', [ 'id_user', 'id_role' ])
            ->values([ 0, 0 ])
            ->values([ 1, 0 ])
            ->values([ 2, 1 ])
            ->values([ 3, 1 ])
            ->values([ 4, 2 ])
            ->values([ 5, 2 ])
            ->execute();

        self::assertFileExists(self::$root . 'user.' . $this->bdd->getExtension());
    }

    public function testGetIncrement()
    {
        self::assertEquals($this->bdd->getIncrement('user'), 6);
        self::assertEquals($this->bdd->getIncrement('role'), 2);
    }

    /**
     * @expectedException \Queryflatfile\Exception\Query\TableNotFoundException
     */
    public function testGetIncrementNoFound()
    {
        self::assertEquals($this->bdd->getIncrement('error'), 1);
    }

    /**
     * @expectedException \Exception
     */
    public function testGetIncrementNoExist()
    {
        self::assertEquals($this->bdd->getIncrement('user_role'), 1);
    }

    public function testCreateTableIfNotExistsData()
    {
        $this->bdd->createTableIfNotExists('user');

        self::assertFileExists(self::$root . 'user.' . $this->bdd->getExtension());
    }

    /**
     * @expectedException \Queryflatfile\Exception\Query\TableNotFoundException
     */
    public function testInsertIntoExceptionTable()
    {
        $this->request->insertInto('foo', [ 'id', 'name', 'firstname' ])->execute();
    }

    /**
     * @expectedException \Queryflatfile\Exception\Query\ColumnsNotFoundException
     */
    public function testInsertIntoExceptionColumn()
    {
        $this->request->insertInto('user', [])->values([ 0, 'NOEL' ])->execute();
    }

    /**
     * @expectedException \Queryflatfile\Exception\Query\ColumnsNotFoundException
     */
    public function testInsertIntoExceptionValue()
    {
        $this->request->insertInto('user', [ 'id', 'name', 'firstname' ])
            ->values([ 0, 'NOEL' ])
            ->execute();
    }

    public function testSelect()
    {
        $data1 = $this->request->select([ 'firstname' ])->from('user')->fetch();
        $data2 = $this->request->select('firstname')->from('user')->fetch();

        self::assertArraySubset($data1, [ 'firstname' => 'Mathieu' ]);
        self::assertArraySubset($data2, [ 'firstname' => 'Mathieu' ]);
    }

    public function testLists()
    {
        $data = $this->request->from('user')->lists('firstname');

        $assert = [ 'Mathieu', 'Jean', 'Manon', 'Marie', 'Pierre', 'Eva', null ];

        self::assertArraySubset($data, $assert);
    }

    public function testListsKey()
    {
        $data = $this->request->from('user')->lists('firstname', 'id');

        self::assertArraySubset($data, [
            0 => 'Mathieu',
            1 => 'Jean',
            2 => 'Manon',
            3 => 'Marie',
            4 => 'Pierre',
            5 => 'Eva',
            6 => null
        ]);
    }

    public function testListsVoid()
    {
        $data = $this->request->from('user')->lists('error');

        self::assertArraySubset($data, []);
    }

    /**
     * @expectedException \Queryflatfile\Exception\Query\ColumnsNotFoundException
     */
    public function testSelectExceptionValue()
    {
        $this->request->select([ 'foo' ])->from('user')->fetch();
    }

    /**
     * @expectedException \Queryflatfile\Exception\Query\TableNotFoundException
     */
    public function testSelectExceptionFrom()
    {
        $this->request->select([ 'firstname' ])->from('foo')->fetch();
    }

    /**
     * @expectedException \Queryflatfile\Exception\Query\TableNotFoundException
     */
    public function testFromException()
    {
        $this->request->select([ 'firstname' ])->fetch();
    }

    public function testSelectAlternative()
    {
        $data1 = $this->request->select()->from('user')->fetch();
        $data2 = $this->request->from('user')->fetch();

        self::assertArraySubset($data1, [ 'id' => 0, 'name' => 'NOEL', 'firstname' => 'Mathieu' ]);
        self::assertArraySubset($data2, [ 'id' => 0, 'name' => 'NOEL', 'firstname' => 'Mathieu' ]);
    }

    /**
     * @expectedException \Queryflatfile\Exception\Query\TableNotFoundException
     */
    public function testSelectAlternativeExceptionFrom()
    {
        $this->request->from('foo')->fetch();
    }

    /**
     * @dataProvider whereEqualsProvider
     */
    public function testWhereEquals($operator, $value, array $arraySubject)
    {
        $data = $this->request->select('name')
            ->from('user')
            ->where('id', $operator, $value)
            ->fetch();

        self::assertArraySubset($data, $arraySubject);
    }

    public function whereEqualsProvider()
    {
        yield [ '=', '1', [] ];
        yield [ '===', '1', [] ];
        yield [ '=', 1, [ 'name' => 'DUPOND' ] ];
        yield [ '===', '1', [ 'name' => 'DUPOND' ] ];
        yield [ '==', '1', [ 'name' => 'DUPOND' ] ];
        yield [ 1, null, [ 'name' => 'DUPOND' ] ];
    }

    /**
     * @expectedException \Queryflatfile\Exception\Query\OperatorNotFound
     */
    public function testWhereOperatorException()
    {
        $this->request->select('name')
            ->from('user')
            ->where('id', 'error', '1')
            ->fetch();
    }

    /**
     * @dataProvider whereNotEqualsProvider
     */
    public function testWhereNotEqualsNoType($operator, $value)
    {
        $data = $this->request->select('firstname')
            ->from('user')
            ->where('id', $operator, $value)
            ->fetchAll();

        $arraySubject = [
            [ 'firstname' => 'Mathieu' ],
            [ 'firstname' => 'Manon' ],
            [ 'firstname' => 'Marie' ],
            [ 'firstname' => 'Pierre' ],
            [ 'firstname' => 'Eva' ],
            [ 'firstname' => null ]
        ];

        /* whereNotEquals sans prendre en compte le type */
        self::assertArraySubset($data, $arraySubject);
    }

    public function whereNotEqualsProvider()
    {
        yield [ '<>', '1' ];
        yield [ '<>', 1 ];
        yield [ '!=', '1' ];
        yield [ '!=', 1 ];
    }

    public function testWhereNotEqualsType()
    {
        /* L'identifiant stocké est un integer, pas un string */
        $dataType1 = $this->request->select('firstname')
            ->from('user')
            ->where('id', '!==', '1')
            ->fetchAll();

        $dataType2 = $this->request->select('firstname')
            ->from('user')
            ->where('id', '!==', 1)
            ->fetchAll();

        self::assertArraySubset($dataType1, [
            [ 'firstname' => 'Mathieu' ],
            [ 'firstname' => 'Jean' ],
            [ 'firstname' => 'Manon' ],
            [ 'firstname' => 'Marie' ],
            [ 'firstname' => 'Pierre' ],
            [ 'firstname' => 'Eva' ],
            [ 'firstname' => null ]
        ]);
        self::assertArraySubset($dataType2, [
            [ 'firstname' => 'Mathieu' ],
            [ 'firstname' => 'Manon' ],
            [ 'firstname' => 'Marie' ],
            [ 'firstname' => 'Pierre' ],
            [ 'firstname' => 'Eva' ],
            [ 'firstname' => null ]
        ]);
    }

    /**
     * @dataProvider whereLessProvider
     */
    public function testWhereLess($operator, array $arraySubject)
    {
        $data = $this->request
            ->from('user')
            ->where('id', $operator, 1)
            ->fetchAll();

        self::assertArraySubset($data, $arraySubject);
    }

    public function whereLessProvider()
    {
        yield [ '<', [
                [ 'id' => 0, 'name' => 'NOEL', 'firstname' => 'Mathieu' ]
            ]
        ];
        yield [ '<=', [
                [ 'id' => 0, 'name' => 'NOEL', 'firstname' => 'Mathieu' ],
                [ 'id' => 1, 'name' => 'DUPOND', 'firstname' => 'Jean' ]
            ]
        ];
    }

    /**
     * @dataProvider whereGreaterProvider
     */
    public function testWhereGreater($operator, array $arraySubject)
    {
        $data = $this->request
            ->from('user')
            ->where('id', $operator, 5)
            ->fetchAll();

        self::assertArraySubset($data, $arraySubject);
    }

    public function whereGreaterProvider()
    {
        yield [ '>', [
                [ 'id' => 6, 'name' => 'ROBERT', 'firstname' => null ]
            ]
        ];
        yield [ '>=', [
                [ 'id' => 5, 'name' => 'MEYER', 'firstname' => 'Eva' ],
                [ 'id' => 6, 'name' => 'ROBERT', 'firstname' => null ]
            ]
        ];
    }

    /**
     * @expectedException \Queryflatfile\Exception\Query\ColumnsNotFoundException
     */
    public function testWhereEqualsExceptionColumn()
    {
        $this->request->select([ 'name' ])
            ->from('user')
            ->where('foo', '=', 'Jean')
            ->fetch();
    }

    public function testWhereBetween()
    {
        $data = $this->request
            ->from('user')
            ->between('id', 1, 2)
            ->fetch();

        self::assertArraySubset($data, [ 'id' => 1, 'name' => 'DUPOND', 'firstname' => 'Jean' ]);
    }

    public function testWhereNotBetween()
    {
        $data = $this->request
            ->from('user')
            ->notBetween('id', 1, 2)
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 0, 'name' => 'NOEL', 'firstname' => 'Mathieu' ],
            [ 'id' => 3, 'name' => null, 'firstname' => 'Marie' ],
            [ 'id' => 4, 'name' => 'DUPOND', 'firstname' => 'Pierre' ],
            [ 'id' => 5, 'name' => 'MEYER', 'firstname' => 'Eva' ],
            [ 'id' => 6, 'name' => 'ROBERT', 'firstname' => null ]
        ]);
    }

    public function testWhereOrBetween()
    {
        $data = $this->request
            ->from('user')
            ->between('id', 1, 2)
            ->orBetween('id', 5, 6)
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 1, 'name' => 'DUPOND', 'firstname' => 'Jean' ],
            [ 'id' => 2, 'name' => 'MARTIN', 'firstname' => 'Manon' ],
            [ 'id' => 5, 'name' => 'MEYER', 'firstname' => 'Eva' ],
            [ 'id' => 6, 'name' => 'ROBERT', 'firstname' => null ]
        ]);
    }

    public function testWhereOrNotBetween()
    {
        $data = $this->request
            ->from('user')
            ->between('id', 1, 2)
            ->orNotBetween('id', 5, 6)
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 0, 'name' => 'NOEL', 'firstname' => 'Mathieu' ],
            [ 'id' => 1, 'name' => 'DUPOND', 'firstname' => 'Jean' ],
            [ 'id' => 2, 'name' => 'MARTIN', 'firstname' => 'Manon' ],
            [ 'id' => 3, 'name' => null, 'firstname' => 'Marie' ],
            [ 'id' => 4, 'name' => 'DUPOND', 'firstname' => 'Pierre' ]
        ]);
    }

    /**
     * @expectedException \Queryflatfile\Exception\Query\ColumnsNotFoundException
     */
    public function testWhereBetweenExceptionColumn()
    {
        $this->request
            ->from('user')
            ->between('foo', 0, 2)
            ->fetch();
    }

    public function testWhereIn()
    {
        $data = $this->request
            ->from('user')
            ->in('id', [ 0, 1 ])
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 0, 'name' => 'NOEL', 'firstname' => 'Mathieu' ],
            [ 'id' => 1, 'name' => 'DUPOND', 'firstname' => 'Jean' ]
        ]);
    }

    public function testWhereNotIn()
    {
        $data = $this->request
            ->from('user')
            ->notIn('id', [ 0, 1 ])
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 2, 'name' => 'MARTIN', 'firstname' => 'Manon' ],
            [ 'id' => 3, 'name' => null, 'firstname' => 'Marie' ],
            [ 'id' => 4, 'name' => 'DUPOND', 'firstname' => 'Pierre' ],
            [ 'id' => 5, 'name' => 'MEYER', 'firstname' => 'Eva' ],
            [ 'id' => 6, 'name' => 'ROBERT', 'firstname' => null ]
        ]);
    }

    public function testWhereOrIn()
    {
        $data = $this->request
            ->from('user')
            ->in('id', [ 0, 1 ])
            ->orIn('id', [ 5, 6 ])
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 0, 'name' => 'NOEL', 'firstname' => 'Mathieu' ],
            [ 'id' => 1, 'name' => 'DUPOND', 'firstname' => 'Jean' ],
            [ 'id' => 5, 'name' => 'MEYER', 'firstname' => 'Eva' ],
            [ 'id' => 6, 'name' => 'ROBERT', 'firstname' => null ]
        ]);
    }

    public function testWhereOrNotIn()
    {
        $data = $this->request
            ->from('user')
            ->in('id', [ 0, 1 ])
            ->orNotIn('id', [ 5, 6 ])
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 0, 'name' => 'NOEL', 'firstname' => 'Mathieu' ],
            [ 'id' => 1, 'name' => 'DUPOND', 'firstname' => 'Jean' ],
            [ 'id' => 2, 'name' => 'MARTIN', 'firstname' => 'Manon' ],
            [ 'id' => 3, 'name' => null, 'firstname' => 'Marie' ],
            [ 'id' => 4, 'name' => 'DUPOND', 'firstname' => 'Pierre' ],
        ]);
    }

    /**
     * @expectedException \Queryflatfile\Exception\Query\ColumnsNotFoundException
     */
    public function testWhereInExceptionColumn()
    {
        $this->request
            ->from('user')
            ->in('foo', [ 0, 1 ])
            ->fetchAll();
    }

    public function testWhereIsNull()
    {
        $data = $this->request
            ->from('user')
            ->isNull('firstname')
            ->fetch();

        self::assertArraySubset($data, [ 'id' => 6, 'name' => 'ROBERT', 'firstname' => null ]);
    }

    public function testWhereIsNotNull()
    {
        $data = $this->request
            ->from('user')
            ->isNotNull('firstname')
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 0, 'name' => 'NOEL', 'firstname' => 'Mathieu' ],
            [ 'id' => 1, 'name' => 'DUPOND', 'firstname' => 'Jean' ],
            [ 'id' => 2, 'name' => 'MARTIN', 'firstname' => 'Manon' ],
            [ 'id' => 3, 'name' => null, 'firstname' => 'Marie' ],
            [ 'id' => 4, 'name' => 'DUPOND', 'firstname' => 'Pierre' ],
            [ 'id' => 5, 'name' => 'MEYER', 'firstname' => 'Eva' ],
        ]);
    }

    public function testWhereOrIsNull()
    {
        $data = $this->request
            ->from('user')
            ->isNull('firstname')
            ->orIsNull('name')
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 3, 'name' => null, 'firstname' => 'Marie' ],
            [ 'id' => 6, 'name' => 'ROBERT', 'firstname' => null ]
        ]);
    }

    public function testWhereOrIsNotNull()
    {
        $data = $this->request
            ->from('user')
            ->isNull('firstname')
            ->orIsNotNull('name')
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 0, 'name' => 'NOEL', 'firstname' => 'Mathieu' ],
            [ 'id' => 1, 'name' => 'DUPOND', 'firstname' => 'Jean' ],
            [ 'id' => 2, 'name' => 'MARTIN', 'firstname' => 'Manon' ],
            [ 'id' => 4, 'name' => 'DUPOND', 'firstname' => 'Pierre' ],
            [ 'id' => 5, 'name' => 'MEYER', 'firstname' => 'Eva' ],
            [ 'id' => 6, 'name' => 'ROBERT', 'firstname' => null ]
        ]);
    }

    /**
     * @expectedException \Queryflatfile\Exception\Query\ColumnsNotFoundException
     */
    public function testWhereIsNullExceptionColumn()
    {
        $this->request
            ->from('user')
            ->isNull('foo')
            ->fetch();
    }

    /**
     * @dataProvider whereLikeProvider
     */
    public function testWhereLike($operator, $value, array $arraySubject)
    {
        $data = $this->request
            ->select('id', 'name')
            ->from('user')
            ->where('name', $operator, $value)
            ->fetchAll();

        self::assertArraySubset($data, $arraySubject);
    }

    public function whereLikeProvider()
    {
        // LIKE
        yield [ 'like', 'DUP%', [
                [ 'id' => 1, 'name' => 'DUPOND' ],
                [ 'id' => 4, 'name' => 'DUPOND' ]
            ]
        ];
        yield [ 'like', '%TI%', [
                [ 'id' => 2, 'name' => 'MARTIN' ]
            ]
        ];
        yield [ 'like', 'OND', [] ];
        yield [ 'like', 'OND%', [] ];
        yield [ 'like', '%OND', [
                [ 'id' => 1, 'name' => 'DUPOND'],
                [ 'id' => 4, 'name' => 'DUPOND']
            ]
        ];
        yield [ 'like', '%OND%', [
                [ 'id' => 1, 'name' => 'DUPOND'],
                [ 'id' => 4, 'name' => 'DUPOND']
            ]
        ];

        // ILIKE
        yield [ 'ilike', 'Dup%', [
                [ 'id' => 1, 'name' => 'DUPOND'],
                [ 'id' => 4, 'name' => 'DUPOND']
            ]
        ];
        yield [ 'ilike', '%OnD', [
                [ 'id' => 1, 'name' => 'DUPOND', 'firstname' => 'Jean' ],
                [ 'id' => 4, 'name' => 'DUPOND', 'firstname' => 'Pierre' ]
            ]
        ];
        yield [ 'ilike', '%ti%', [
                [ 'id' => 2, 'name' => 'MARTIN', 'firstname' => 'Manon' ]
            ]
        ];

        // NOT LIKE
        yield [ 'not like', 'DUP%', [
                [ 'id' => 0, 'name' => 'NOEL' ],
                [ 'id' => 2, 'name' => 'MARTIN' ],
                [ 'id' => 5, 'name' => 'MEYER' ],
                [ 'id' => 6, 'name' => 'ROBERT' ]
            ]
        ];
        yield [ 'not like', '%OND', [
                [ 'id' => 0, 'name' => 'NOEL' ],
                [ 'id' => 2, 'name' => 'MARTIN' ],
                [ 'id' => 5, 'name' => 'MEYER' ],
                [ 'id' => 6, 'name' => 'ROBERT' ]
            ]
        ];
        yield [ 'not like', '%E%', [
                [ 'id' => 1, 'name' => 'DUPOND' ],
                [ 'id' => 2, 'name' => 'MARTIN' ],
                [ 'id' => 4, 'name' => 'DUPOND' ]
            ]
        ];

        // NOT ILIKE
        yield [ 'not ilike', 'DuP%', [
                [ 'id' => 0, 'name' => 'NOEL' ],
                [ 'id' => 2, 'name' => 'MARTIN' ],
                [ 'id' => 5, 'name' => 'MEYER' ],
                [ 'id' => 6, 'name' => 'ROBERT' ]
            ]
        ];
        yield [ 'not ilike', '%D', [
                [ 'id' => 0, 'name' => 'NOEL' ],
                [ 'id' => 2, 'name' => 'MARTIN' ],
                [ 'id' => 5, 'name' => 'MEYER' ],
                [ 'id' => 6, 'name' => 'ROBERT' ]
            ]
        ];
        yield [ 'not ilike', '%E%', [
                [ 'id' => 1, 'name' => 'DUPOND' ],
                [ 'id' => 2, 'name' => 'MARTIN' ],
                [ 'id' => 4, 'name' => 'DUPOND' ]
            ]
        ];
    }

    public function testWhereRegex()
    {
        $data = $this->request
            ->from('user')
            ->regex('name', '/^D/')
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 1, 'name' => 'DUPOND', 'firstname' => 'Jean' ],
            [ 'id' => 4, 'name' => 'DUPOND', 'firstname' => 'Pierre' ]
        ]);
    }

    public function testWhereNotRegex()
    {
        $data = $this->request
            ->from('user')
            ->notRegex('name', '/^D/')
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 0, 'name' => 'NOEL', 'firstname' => 'Mathieu' ],
            [ 'id' => 2, 'name' => 'MARTIN', 'firstname' => 'Manon' ],
            [ 'id' => 5, 'name' => 'MEYER', 'firstname' => 'Eva' ],
            [ 'id' => 6, 'name' => 'ROBERT', 'firstname' => null ]
        ]);
    }

    public function testWhereOrNotRegex()
    {
        $data = $this->request
            ->from('user')
            ->regex('name', '/^D/')
            ->orNotRegex('firstname', '/^M/')
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 1, 'name' => 'DUPOND', 'firstname' => 'Jean' ],
            [ 'id' => 4, 'name' => 'DUPOND', 'firstname' => 'Pierre' ],
            [ 'id' => 5, 'name' => 'MEYER', 'firstname' => 'Eva' ]
        ]);
    }

    public function testWhereOrRegex()
    {
        $data = $this->request
            ->from('user')
            ->regex('name', '/^D/')
            ->orRegex('name', '/^N/')
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 0, 'name' => 'NOEL', 'firstname' => 'Mathieu' ],
            [ 'id' => 1, 'name' => 'DUPOND', 'firstname' => 'Jean' ],
            [ 'id' => 4, 'name' => 'DUPOND', 'firstname' => 'Pierre' ] ]);
    }

    /**
     * @expectedException \Queryflatfile\Exception\Query\ColumnsNotFoundException
     */
    public function testWhereRegexExceptionColumns()
    {
        $this->request
            ->from('user')
            ->regex('foo', '/^D/')
            ->fetch();
    }

    public function testAndWhere()
    {
        $data = $this->request
            ->from('user')
            ->where('name', 'DUPOND')
            ->where('firstname', 'Pierre')
            ->fetch();

        self::assertArraySubset($data, [ 'id' => 4, 'name' => 'DUPOND', 'firstname' => 'Pierre' ]);
    }

    public function testOrWhere()
    {
        $data = $this->request
            ->from('user')
            ->where('name', 'DUPOND')
            ->orWhere('firstname', 'Mathieu')
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 0, 'name' => 'NOEL', 'firstname' => 'Mathieu' ],
            [ 'id' => 1, 'name' => 'DUPOND', 'firstname' => 'Jean' ],
            [ 'id' => 4, 'name' => 'DUPOND', 'firstname' => 'Pierre' ]
        ]);
    }

    public function testOrNotWhere()
    {
        $data = $this->request
            ->from('user')
            ->where('name', 'DUPOND')
            ->orNotWhere('firstname', 'Mathieu')
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 1, 'name' => 'DUPOND', 'firstname' => 'Jean' ],
            [ 'id' => 2, 'name' => 'MARTIN', 'firstname' => 'Manon' ],
            [ 'id' => 3, 'name' => null, 'firstname' => 'Marie' ],
            [ 'id' => 4, 'name' => 'DUPOND', 'firstname' => 'Pierre' ],
            [ 'id' => 5, 'name' => 'MEYER', 'firstname' => 'Eva' ],
            [ 'id' => 6, 'name' => 'ROBERT', 'firstname' => null ]
        ]);
    }

    public function testWhereAndGroup()
    {
        $data = $this->request
            ->from('user')
            ->where('id', '>=', 2)
            ->where(static function ($query) {
                $query->where('name', 'DUPOND')
                ->orWhere('firstname', 'Eva');
            })
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 4, 'name' => 'DUPOND', 'firstname' => 'Pierre' ],
            [ 'id' => 5, 'name' => 'MEYER', 'firstname' => 'Eva' ]
        ]);
    }

    public function testWhereOrGroup()
    {
        $data = $this->request
            ->from('user')
            ->where('name', 'DUPOND')
            ->orWhere(static function ($query) {
                $query->where('firstname', 'Eva')
                ->orWhere('firstname', 'Mathieu');
            })
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 0, 'name' => 'NOEL', 'firstname' => 'Mathieu' ],
            [ 'id' => 1, 'name' => 'DUPOND', 'firstname' => 'Jean' ],
            [ 'id' => 4, 'name' => 'DUPOND', 'firstname' => 'Pierre' ],
            [ 'id' => 5, 'name' => 'MEYER', 'firstname' => 'Eva' ]
        ]);
    }

    public function testLimit()
    {
        $data = $this->request
            ->from('user')
            ->limit(1)
            ->fetchAll();

        self::assertArraySubset($data, [ [ 'id' => 0, 'name' => 'NOEL', 'firstname' => 'Mathieu' ] ]);
    }

    /**
     * @expectedException \Queryflatfile\Exception\Query\QueryException
     */
    public function testLimitException()
    {
        $this->request
            ->from('user')
            ->limit(-1)
            ->fetchAll();
    }

    public function testLimitOffset()
    {
        $data = $this->request
            ->from('user')
            ->limit(1, 1)
            ->fetchAll();

        self::assertArraySubset($data, [ [ 'id' => 1, 'name' => 'DUPOND', 'firstname' => 'Jean' ] ]);
    }

    /**
     * @expectedException \Queryflatfile\Exception\Query\QueryException
     */
    public function testOffsetException()
    {
        $this->request
            ->from('user')
            ->limit(1, -1)
            ->fetchAll();
    }

    public function testLimitOffsetWhere()
    {
        $data = $this->request
            ->from('user')
            ->where('name', '=', 'DUPOND')
            ->limit(1, 1)
            ->fetchAll();

        self::assertArraySubset($data, [ [ 'id' => 4, 'name' => 'DUPOND', 'firstname' => 'Pierre' ] ]);
    }

    public function testOrderByAsc()
    {
        $data = $this->request->select([ 'firstname' ])
            ->from('user')
            ->orderBy('firstname')
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'firstname' => null ],
            [ 'firstname' => 'Eva' ],
            [ 'firstname' => 'Jean' ],
            [ 'firstname' => 'Manon' ],
            [ 'firstname' => 'Marie' ],
            [ 'firstname' => 'Mathieu' ],
            [ 'firstname' => 'Pierre' ]
        ]);
    }

    public function testOrderByDesc()
    {
        $data = $this->request->select([ 'firstname' ])
            ->from('user')
            ->orderBy('firstname', SORT_DESC)
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'firstname' => 'Pierre' ],
            [ 'firstname' => 'Mathieu' ],
            [ 'firstname' => 'Marie' ],
            [ 'firstname' => 'Manon' ],
            [ 'firstname' => 'Jean' ],
            [ 'firstname' => 'Eva' ],
            [ 'firstname' => null ]
        ]);
    }

    /**
     * @expectedException \Queryflatfile\Exception\Query\OperatorNotFound
     */
    public function testOrderByException()
    {
        $this->request
            ->from('user')
            ->orderBy('firstname', 'error')
            ->fetchAll();
    }

    public function testOrderByDescFetch()
    {
        $data = $this->request->select([ 'name' ])
            ->from('user')
            ->where('id', '>=', 4)
            ->orderBy('name', SORT_DESC)
            ->fetch();

        self::assertArraySubset($data, [ 'name' => 'ROBERT' ]);
    }

    public function testOrderByDescLimitOffset()
    {
        $data = $this->request->select([ 'name' ])
            ->from('user')
            ->where('id', '>=', 3)
            ->orderBy('name', SORT_DESC)
            ->limit(2, 1)
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'name' => 'MEYER' ],
            [ 'name' => 'DUPOND' ]
        ]);
    }

    public function testOrderByMultipleAsc()
    {
        $data = $this->request->select([ 'name', 'firstname' ])
            ->from('user')
            ->orderBy('name', SORT_DESC)
            ->orderBy('firstname')
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'name' => 'ROBERT', 'firstname' => null ],
            [ 'name' => 'NOEL', 'firstname' => 'Mathieu' ],
            [ 'name' => 'MEYER', 'firstname' => 'Eva' ],
            [ 'name' => 'MARTIN', 'firstname' => 'Manon' ],
            [ 'name' => 'DUPOND', 'firstname' => 'Jean' ],
            [ 'name' => 'DUPOND', 'firstname' => 'Pierre' ],
            [ 'name' => null, 'firstname' => 'Marie' ]
        ]);
    }

    public function testOrderByMultipleDesc()
    {
        $data = $this->request->select([ 'name', 'firstname' ])
            ->from('user')
            ->orderBy('name', SORT_DESC)
            ->orderBy('firstname', SORT_DESC)
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'name' => 'ROBERT', 'firstname' => null ],
            [ 'name' => 'NOEL', 'firstname' => 'Mathieu' ],
            [ 'name' => 'MEYER', 'firstname' => 'Eva' ],
            [ 'name' => 'MARTIN', 'firstname' => 'Manon' ],
            [ 'name' => 'DUPOND', 'firstname' => 'Pierre' ],
            [ 'name' => 'DUPOND', 'firstname' => 'Jean' ],
            [ 'name' => null, 'firstname' => 'Marie' ]
        ]);
    }

    public function testRightJoin()
    {
        $data = $this->request->select('id', 'name', 'firstname', 'labelle')
            ->from('role')
            ->rightJoin('user_role', 'id_role', '=', 'user_role.id_role')
            ->rightJoin('user', 'id_user', '=', 'user.id')
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 0, 'name' => 'NOEL', 'firstname' => 'Mathieu', 'labelle' => 'Admin' ],
            [ 'id' => 1, 'name' => 'DUPOND', 'firstname' => 'Jean', 'labelle' => 'Admin' ],
            [ 'id' => 2, 'name' => 'MARTIN', 'firstname' => 'Manon', 'labelle' => 'Author' ],
            [ 'id' => 3, 'name' => null, 'firstname' => 'Marie', 'labelle' => 'Author' ],
            [ 'id' => 4, 'name' => 'DUPOND', 'firstname' => 'Pierre', 'labelle' => 'User' ],
            [ 'id' => 5, 'name' => 'MEYER', 'firstname' => 'Eva', 'labelle' => 'User' ],
            /* Pas de corespondance pour ROBERT donc le reste des colonnes en null */
            [ 'id' => 6, 'name' => 'ROBERT', 'firstname' => null, 'labelle' => null ],
        ]);
    }

    public function testLeftJoin()
    {
        $data = $this->request->select('id', 'name', 'firstname', 'labelle')
            ->from('user')
            ->leftJoin('user_role', 'id', '=', 'user_role.id_user')
            ->leftJoin('role', 'id_role', '=', 'role.id_role')
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 0, 'name' => 'NOEL', 'firstname' => 'Mathieu', 'labelle' => 'Admin' ],
            [ 'id' => 1, 'name' => 'DUPOND', 'firstname' => 'Jean', 'labelle' => 'Admin' ],
            [ 'id' => 2, 'name' => 'MARTIN', 'firstname' => 'Manon', 'labelle' => 'Author' ],
            [ 'id' => 3, 'name' => null, 'firstname' => 'Marie', 'labelle' => 'Author' ],
            [ 'id' => 4, 'name' => 'DUPOND', 'firstname' => 'Pierre', 'labelle' => 'User' ],
            [ 'id' => 5, 'name' => 'MEYER', 'firstname' => 'Eva', 'labelle' => 'User' ],
            /* Pas de corespondance pour ROBERT donc le reste des colonnes en null */
            [ 'id' => 6, 'name' => 'ROBERT', 'firstname' => null, 'labelle' => null ],
        ]);
    }

    public function testLeftJoinWhere()
    {
        $data = $this->request->select('id', 'name', 'firstname')
            ->from('user')
            ->leftJoin('user_role', 'id', '=', 'user_role.id_user')
            ->leftJoin('role', 'id_role', '=', 'role.id_role')
            ->where('labelle', 'Admin')
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 0, 'name' => 'NOEL', 'firstname' => 'Mathieu' ],
            [ 'id' => 1, 'name' => 'DUPOND', 'firstname' => 'Jean' ],
        ]);
    }

    public function testLeftJoinGroup()
    {
        $data = $this->request->select('id', 'name', 'firstname')
            ->from('user')
            ->leftJoin('user_role', static function ($query) {
                $query->where('id', '=', 'user_role.id_user');
            })
            ->leftJoin('role', 'id_role', '=', 'role.id_role')
            ->where('labelle', 'Admin')
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 0, 'name' => 'NOEL', 'firstname' => 'Mathieu' ],
            [ 'id' => 1, 'name' => 'DUPOND', 'firstname' => 'Jean' ],
        ]);
    }

    public function testLeftJoinGroupMultiple()
    {
        $data = $this->request->select('id', 'name', 'firstname', 'labelle')
            ->from('user')
            ->leftJoin('user_role', 'id', '=', 'user_role.id_user')
            ->leftJoin('role', static function ($query) {
                $query->where(static function ($query) {
                    $query->where('id_role', '=', 'role.id_role');
                });
            })
            ->where('labelle', 'Admin')
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 0, 'name' => 'NOEL', 'firstname' => 'Mathieu' ],
            [ 'id' => 1, 'name' => 'DUPOND', 'firstname' => 'Jean' ],
        ]);
    }

    public function testRightJoinGroupe()
    {
        $data = $this->request->select('id', 'name', 'firstname', 'labelle')
            ->from('role')
            ->rightJoin('user_role', 'id_role', '=', 'user_role.id_role')
            ->rightJoin('user', static function ($query) {
                $query->where('id_user', '=', 'user.id');
            })
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 0, 'name' => 'NOEL', 'firstname' => 'Mathieu', 'labelle' => 'Admin' ],
            [ 'id' => 1, 'name' => 'DUPOND', 'firstname' => 'Jean', 'labelle' => 'Admin' ],
            [ 'id' => 2, 'name' => 'MARTIN', 'firstname' => 'Manon', 'labelle' => 'Author' ],
            [ 'id' => 3, 'name' => null, 'firstname' => 'Marie', 'labelle' => 'Author' ],
            [ 'id' => 4, 'name' => 'DUPOND', 'firstname' => 'Pierre', 'labelle' => 'User' ],
            [ 'id' => 5, 'name' => 'MEYER', 'firstname' => 'Eva', 'labelle' => 'User' ],
            /* Pas de corespondance pour ROBERT donc le reste des colonnes en null */
            [ 'id' => 6, 'name' => 'ROBERT', 'firstname' => null, 'labelle' => null ],
        ]);
    }

    /**
     * @expectedException \Queryflatfile\Exception\Query\ColumnsNotFoundException
     */
    public function testLeftJoinExceptionColumn()
    {
        $this->request->select([ 'id', 'name', 'firstname' ])
            ->from('user')
            ->leftJoin('user_role', 'foo', '==', 'user_role.id_user')
            ->leftJoin('role', 'id_role', '==', 'role.id')
            ->where('labelle', 'Admin')
            ->fetch();
    }

    public function testUnion()
    {
        $union = $this->request
            ->select('name')
            ->from('user')
            ->between('id', 1, 5);

        $data = $this->request2
            ->select('name')
            ->from('user')
            ->union($union)
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'name' => 'NOEL' ],
            [ 'name' => 'DUPOND' ],
            [ 'name' => 'MARTIN' ],
            [ 'name' => null ],
            [ 'name' => 'MEYER' ],
            [ 'name' => 'ROBERT' ]
        ]);
    }

    public function testUnionMultiple()
    {
        $union = $this->request
            ->select('name', 'firstname')
            ->from('user')
            ->between('id', 1, 5);

        $data = $this->request2
            ->select('name', 'firstname')
            ->from('user')
            ->union($union)
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'name' => 'NOEL', 'firstname' => 'Mathieu' ],
            [ 'name' => 'DUPOND', 'firstname' => 'Jean' ],
            [ 'name' => 'MARTIN', 'firstname' => 'Manon' ],
            [ 'name' => null, 'firstname' => 'Marie' ],
            [ 'name' => 'DUPOND', 'firstname' => 'Pierre' ],
            [ 'name' => 'MEYER', 'firstname' => 'Eva' ],
            [ 'name' => 'ROBERT', 'firstname' => null ]
        ]);
    }

    /**
     * @expectedException \Queryflatfile\Exception\Query\ColumnsNotFoundException
     */
    public function testUnionMultipleException()
    {
        $union = $this->request
            ->select('name')
            ->from('user')
            ->between('id', 1, 5);

        $this->request2
            ->select('name', 'firstname')
            ->from('user')
            ->union($union)
            ->fetchAll();
    }

    public function testUnionAll()
    {
        $union = $this->request
            ->select('name')
            ->from('user')
            ->between('id', 1, 5);

        $data = $this->request2
            ->select('name')
            ->from('user')
            ->unionAll($union)
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'name' => 'NOEL' ],
            [ 'name' => 'DUPOND' ],
            [ 'name' => 'MARTIN' ],
            [ 'name' => null ],
            [ 'name' => 'DUPOND' ],
            [ 'name' => 'MEYER' ],
            [ 'name' => 'ROBERT' ],
            [ 'name' => 'DUPOND' ],
            [ 'name' => 'MARTIN' ],
            [ 'name' => null ],
            [ 'name' => 'DUPOND' ],
            [ 'name' => 'MEYER' ]
        ]);
    }

    public function testUnionAllMultiple()
    {
        $union = $this->request
            ->select('name', 'firstname')
            ->from('user')
            ->between('id', 1, 5);

        $data = $this->request2
            ->select('name', 'firstname')
            ->from('user')
            ->unionAll($union)
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'name' => 'NOEL', 'firstname' => 'Mathieu' ],
            [ 'name' => 'DUPOND', 'firstname' => 'Jean' ],
            [ 'name' => 'MARTIN', 'firstname' => 'Manon' ],
            [ 'name' => null, 'firstname' => 'Marie' ],
            [ 'name' => 'DUPOND', 'firstname' => 'Pierre' ],
            [ 'name' => 'MEYER', 'firstname' => 'Eva' ],
            [ 'name' => 'ROBERT', 'firstname' => null ],
            [ 'name' => 'DUPOND', 'firstname' => 'Jean' ],
            [ 'name' => 'MARTIN', 'firstname' => 'Manon' ],
            [ 'name' => null, 'firstname' => 'Marie' ],
            [ 'name' => 'DUPOND', 'firstname' => 'Pierre' ],
            [ 'name' => 'MEYER', 'firstname' => 'Eva' ]
        ]);
    }

    /**
     * @expectedException \Queryflatfile\Exception\Query\ColumnsNotFoundException
     */
    public function testUnionAllMultipleException()
    {
        $union = $this->request
            ->select('name')
            ->from('user')
            ->between('id', 1, 5);

        $this->request2
            ->select('name', 'firstname')
            ->from('user')
            ->unionAll($union)
            ->fetchAll();
    }

    public function testUnionList()
    {
        $union = $this->request
            ->select('name')
            ->from('user')
            ->between('id', 1, 5);

        $data = $this->request2
            ->select('name')
            ->from('user')
            ->union($union)
            ->lists('name');

        self::assertArraySubset($data, [
            'NOEL',
            'DUPOND',
            'MARTIN',
            null,
            'MEYER',
            'ROBERT'
        ]);
    }

    public function testUnionListOrder()
    {
        $union = $this->request
            ->select('name')
            ->from('user')
            ->between('id', 1, 5);

        $data = $this->request2
            ->select('name')
            ->from('user')
            ->union($union)
            ->orderBy('name')
            ->lists('name');

        self::assertArraySubset($data, [
            null,
            'DUPOND',
            'MARTIN',
            'MEYER',
            'NOEL',
            'ROBERT'
        ]);
    }

    /**
     * @expectedException \Queryflatfile\Exception\Query\BadFunctionException
     */
    public function testExecuteException()
    {
        $this->request
            ->from('user')
            ->where('id', '=', 0)
            ->execute();
    }

    public function testUpdateData()
    {
        $this->request->update('user', [ 'name' => 'PETIT' ])
            ->where('id', '=', 0)
            ->execute();

        $data = $this->request
            ->from('user')
            ->where('id', '=', 0)
            ->fetch();

        self::assertArraySubset($data, [ 'id' => 0, 'name' => 'PETIT', 'firstname' => 'Mathieu' ]);
    }

    public function testUpdateDataFull()
    {
        $this->request->update('user', [ 'name' => 'PETIT' ])
            ->execute();

        $data = $this->request
            ->from('user')
            ->where('id', '=', 0)
            ->fetch();

        self::assertArraySubset($data, [ 'id' => 0, 'name' => 'PETIT', 'firstname' => 'Mathieu' ]);
    }

    public function testDeleteData()
    {
        $this->request->from('user')
            ->delete()
            ->between('id', 1, 4)
            ->execute();

        $data = $this->request
            ->from('user')
            ->fetchAll();

        self::assertArraySubset($data, [
            [ 'id' => 0, 'name' => 'PETIT', 'firstname' => 'Mathieu' ],
            [ 'id' => 5, 'name' => 'PETIT', 'firstname' => 'Eva' ],
            [ 'id' => 6, 'name' => 'PETIT', 'firstname' => null ]
        ]);
    }

    public function testDropTable()
    {
        $this->bdd->dropTable('user_role');

        self::assertFileNotExists(__DIR__ . '/data/user_role.json');
    }

    public function testDropSchema()
    {
        $this->bdd->dropSchema();
        self::assertFileNotExists(__DIR__ . '/data/schema.json');
    }

    /**
     * @expectedException \Exception
     */
    public function testPredicate()
    {
        \Queryflatfile\Where::predicate('', 'error', '');
    }
}
