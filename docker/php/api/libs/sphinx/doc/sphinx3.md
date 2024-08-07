Sphinx 3
=========

Sphinx is a free, dual-licensed search server. Sphinx is written in C++,
and focuses on query performance and search relevance.

The primary client API is currently SphinxQL, a dialect of SQL. Almost any
MySQL connector should work. Additionally, basic HTTP/JSON API and native APIs
for a number of languages (PHP, Python, Ruby, C, Java) are provided.

This document is an effort to build a better documentation for Sphinx v.3.x
and up. Think of it as a book or a tutorial which you could actually *read*;
think of the previous "reference manual" as of a "dictionary" where you look up
specific syntax features. The two might (and should) eventually converge.


Features overview
------------------

Top level picture, what does Sphinx offer?

  * SQL, HTTP/JSON, and custom native SphinxAPI access APIs
  * NRT (Near Real Time) and offline batch indexing
  * Full-text and non-text (parameter) searching
  * Relevance ranking, from basic formulas to ML models
  * Federated results from multiple servers
  * Decent performance

Other things that seem worth mentioning (this list is probably incomplete at
all times, and definitely in random order):

  * Morphology and text-processing tools
    * Fully flexible tokenization (see `charset_table` and `exceptions`)
    * Proper morphology (lemmatizer) for English, Russian, and German
      (see `morphology`)
    * Basic morphology (stemmer) for many other languages
    * User-specified mappings, `core 2 duo => c2d`
  * Native JSON support
  * Geosearch support
  * Fast expressions engine
  * Query suggestions
  * Snippets builder
  * ...

And, of course, there is always stuff that we know we currently lack!

  * Index replication
  * ...


Features cheat sheet
---------------------

This section is supposed to provide a bit more detail on all the available
features; to cover them more or less fully; and give you some further pointers
into the specific reference sections (on the related config directives and
SphinxQL statements).

  * Full-text search queries, see `SELECT ... WHERE MATCH('this')` SphinxQL
    statement
    * Boolean matching operators (implicit AND, explicit OR, NOT, and brackets),
      as in `(one two) | (three !four)`
    * Boolean matching optimizations, see `OPTION boolean_simplify=1` in
      `SELECT` statement
    * Advanced text matching operators
      * Field restrictions, `@title hello world` or `@!title hello` or
        `@(title,body) any of the two` etc
      * In-field position restrictions, `@title[50] hello`
      * MAYBE operator for optional keyword matching, `cat MAYBE dog`
      * phrase matching, `"roses are red"`
      * quorum matching, `"pick any 3 keywords out of this entire set"/3`
      * proximity matching, `"within 10 positions all terms in yoda order"~10`
        or `hello NEAR/3 world NEAR/4 "my test"`
      * strict order matching, `(bag of words) << "exact phrase" << this|that`
      * sentence matching, `all SENTENCE words SENTENCE "in one sentence"`
      * paragraph matching, `"Bill Gates" PARAGRAPH "Steve Jobs"`
      * zone and zone-span matching, `ZONE:(h3,h4) in any of these title tags`
        and `ZONESPAN:(h2) only in a single instance`
    * Keyword modifiers (that can usually be used within operators)
      * exact (pre-morphology) form modifier, `raining =cats and =dogs`
      * field-start and field-end modifiers, `^hello world$`
      * IDF (ranking) boost, `boosted^1.234`
    * Substring and wildcard searches
      * see `min_prefix_len` and `min_infix_len` directives
      * use `th?se three keyword% wild*cards *verywher*` (`?` = 1 char exactly;
        `%` = 0 or 1 char; `*` = 0 or more chars)
  * ...

TODO: describe more, add links!


Getting started
----------------

That should now be rather simple. No magic installation required! On any
platform, the *sufficient* thing to do is:

  1. Get the binaries.
  2. Run `searchd`.
  3. Create the RT indexes.
  4. Run queries.

This is the **easiest** way to get up and running. Sphinx RT indexes (and yes,
"RT" stands for "real-time") are very much like SQL tables. So you run the usual
`CREATE TABLE` query to create an RT index, then run a few `INSERT` queries to
populate that index with data, then a `SELECT` to search, and so on. See more
details on all that just below.

Or alternatively, you can also ETL your existing data stored in SQL (or CSV or
XML) "offline", using the `indexer` tool. That requires a config, as `indexer`
needs to know where to fetch the index data from.

  1. Get the binaries.
  2. Create `sphinx.conf`, with at least 1 `index` section.
  3. Run `indexer build --all` once, to initially create the "plain" indexes.
  4. Run `searchd`.
  5. Run queries.
  6. Run `indexer build --rotate --all` regularly, to "update" the indexes.

This in turn is the easiest way to index (and search!) your **existing** data
stored in something that `indexer` supports. `indexer` can then grab data from
your SQL database (or a plain file); process that data "offline" and (re)build
a so-called "plain" index; and then hand that off to `searchd` for searching.
"Plain" indexes are a bit limited compared to "RT" indexes, but can be easily
"converted" to RT. Again, more details below, we discuss this approach in the
["Writing your first config"](#writing-your-first-config) section.

For now, back to simple fun "online" searching with RT indexes!

### Getting started on Linux (and MacOS)

Versions and file names *will* vary, and you most likely *will* want to
configure Sphinx at least a little, but for an immediate quickstart:

```
$ wget -q https://sphinxsearch.com/files/sphinx-3.6.1-c9dbeda-linux-amd64.tar.gz
$ tar zxf sphinx-3.6.1-c9dbeda-linux-amd64.tar.gz
$ cd sphinx-3.6.1/bin/
$ ./searchd
Sphinx 3.6.1 (commit c9dbedab)
Copyright (c) 2001-2023, Andrew Aksyonoff
Copyright (c) 2008-2016, Sphinx Technologies Inc (http://sphinxsearch.com)

no config file and no datadir, using './sphinxdata'...
listening on all interfaces, port=9312
listening on all interfaces, port=9306
loading 0 indexes...
$
```

That's it! The daemon should now be running and accepting connections on port
9306 in background. And you can connect to it using MySQL CLI (see below for
more details, or just try `mysql -P9306` right away).

For the record, to stop the daemon cleanly, you can either run it with `--stop`
switch, or just kill it with `SIGTERM` (it properly handles that signal).

```
$ ./searchd --stop
Sphinx 3.6.1 (commit c9dbedab)
Copyright (c) 2001-2023, Andrew Aksyonoff
Copyright (c) 2008-2016, Sphinx Technologies Inc (http://sphinxsearch.com)

no config file and no datadir, using './sphinxdata'...
stop: successfully sent SIGTERM to pid 3337005
```

Now to querying (just after a tiny detour for Windows users).

### Getting started on Windows

Pretty much the same story, except that on Windows `searchd` does **not**
automatically go into background.

```
C:\sphinx-3.6.1\bin>searchd.exe
Sphinx 3.6.1-dev (commit c9dbedabf)
Copyright (c) 2001-2023, Andrew Aksyonoff
Copyright (c) 2008-2016, Sphinx Technologies Inc (http://sphinxsearch.com)

no config file and no datadir, using './sphinxdata'...
listening on all interfaces, port=9312
listening on all interfaces, port=9306
loading 0 indexes...
accepting connections
```

This is alright. It isn't hanging, it's waiting for you queries. Do not kill it.
Just switch to a separate session and start querying.

### Running queries via MySQL shell

Run the MySQL CLI and point it to a port 9306. For example on Windows:

```
C:\>mysql -h127.0.0.1 -P9306
Welcome to the MySQL monitor.  Commands end with ; or \g.
Your MySQL connection id is 1
Server version: 3.0-dev (c3c241f)
...
```

I have intentionally used `127.0.0.1` in this example for two reasons (both
caused by MySQL CLI quirks, not Sphinx):

  * sometimes, an IP address is required to use the `-P9306` switch,
    not `localhost`
  * sometimes, `localhost` works but causes a connection delay

But in the simplest case even just `mysql -P9306` should work fine.

And from there, just run some SphinxQL queries!

```sql
mysql> CREATE TABLE test (id bigint, title field stored, content field stored,
    -> gid uint);
Query OK, 0 rows affected (0.00 sec)

mysql> INSERT INTO test (id, title) VALUES (123, 'hello world');
Query OK, 1 row affected (0.00 sec)

mysql> INSERT INTO test (id, gid, content) VALUES (234, 345, 'empty title');
Query OK, 1 row affected (0.00 sec)

mysql> SELECT * FROM test;
+------+------+-------------+-------------+
| id   | gid  | title       | content     |
+------+------+-------------+-------------+
|  123 |    0 | hello world |             |
|  234 |  345 |             | empty title |
+------+------+-------------+-------------+
2 rows in set (0.00 sec)

mysql> SELECT * FROM test WHERE MATCH('hello');
+------+------+-------------+---------+
| id   | gid  | title       | content |
+------+------+-------------+---------+
|  123 |    0 | hello world |         |
+------+------+-------------+---------+
1 row in set (0.00 sec)

mysql> SELECT * FROM test WHERE MATCH('@content hello');
Empty set (0.00 sec)
```

SphinxQL is our own SQL dialect, described in more detail in the respective
[SphinxQL Reference](#sphinxql-reference) section. Simply read on for the most
important basics, though, we discuss them a little below.

Before we begin, though, this (simplest) example only uses `searchd`, and while
that's also fine, there's a different, convenient operational mode where you can
**easily index your pre-existing SQL data** using the `indexer` tool.

The bundled `etc/sphinx-min.conf.dist` and `etc/example.sql` example files show
exactly that. ["Writing your first config"](#writing-your-first-config) section
below steps through that example and explains everything.

Now back to CREATEs, INSERTs, and SELECTs. Alright, so what just happened?!


### SphinxQL basics

We just created our first **full-text index** with a `CREATE TABLE` statement,
called `test` (naturally).

```sql
CREATE TABLE test (
  id BIGINT,
  title FIELD STORED,
  content FIELD STORED,
  gid UINT);
```

Even though we're using MySQL client, we're talking to Sphinx here, not MySQL!
And we're using its SQL dialect (with `FIELD` and `UINT` etc).

We configured 2 full-text **fields** called `title` and `content` respectively,
and 1 integer **attribute** called `gid` (group ID, whatever that might be).

We then issued a couple of `INSERT` queries, and that inserted a couple rows
into our index. Formally those are called **documents**, but we will use both
terms interchangeably.

Once `INSERT` says OK, those rows (aka documents!) become immediately available
for `SELECT` queries. Because **RT index** is "real-time" like that.

```
mysql> SELECT * FROM test;
+------+------+-------------+-------------+
| id   | gid  | title       | content     |
+------+------+-------------+-------------+
|  123 |    0 | hello world |             |
|  234 |  345 |             | empty title |
+------+------+-------------+-------------+
2 rows in set (0.00 sec)
```

Now, what was that `STORED` thingy all about? That enables **DocStore** and
explicitly tells Sphinx to **store the original field text** into our full-text
index. And what if we don't?

```
mysql> CREATE TABLE test2 (id BIGINT, title FIELD, gid UINT);
Query OK, 0 rows affected (0.00 sec)

mysql> INSERT INTO test2 (id, title) VALUES (321, 'hello world');
Query OK, 1 row affected (0.00 sec)

mysql> SELECT * FROM test2;
+------+------+
| id   | gid  |
+------+------+
|  321 |    0 |
+------+------+
1 row in set (0.00 sec)
```

As you see, **by default Sphinx does not store the original field text**, and
**only** keeps the full-text index. So you can search but you can't read those
fields. A bit more details on that are in ["Using DocStore"](#using-docstore)
section.

Text searches with `MATCH()` are going to work at all times. Whether we have
DocStore or not. Because Sphinx is a full-text search engine first.

```
mysql> SELECT * FROM test WHERE MATCH('hello');
+------+------+-------------+---------+
| id   | gid  | title       | content |
+------+------+-------------+---------+
|  123 |    0 | hello world |         |
+------+------+-------------+---------+
1 row in set (0.00 sec)

mysql> SELECT * FROM test2 WHERE MATCH('hello');
+------+------+
| id   | gid  |
+------+------+
|  321 |    0 |
+------+------+
1 row in set (0.00 sec)
```

Then we used **full-text query syntax** to run a fancier query than just simply
matching `hello` in any (full-text indexed) **field**. We limited our searches
to the `content` field and... got zero results.

```
mysql> SELECT * FROM test WHERE MATCH('@content hello');
Empty set (0.00 sec)
```

But that's as expected. Our greetings were in the title, right?

```
mysql> SELECT *, WEIGHT() FROM test WHERE MATCH('@title hello');
+------+-------------+---------+------+-----------+
| id   | title       | content | gid  | weight()  |
+------+-------------+---------+------+-----------+
|  123 | hello world |         |    0 | 10315.066 |
+------+-------------+---------+------+-----------+
1 row in set (0.00 sec)
```

Right. By default `MATCH()` only matches documents (aka rows) that have **all**
the keywords, but those matching keywords are allowed to occur **anywhere** in
the document, in **any** of the indexed fields.

```
mysql> INSERT INTO test (id, title, content) VALUES (1212, 'one', 'two');
Query OK, 1 row affected (0.00 sec)

mysql> SELECT * FROM test WHERE MATCH('one two');
+------+-------+---------+------+
| id   | title | content | gid  |
+------+-------+---------+------+
| 1212 | one   | two     |    0 |
+------+-------+---------+------+
1 row in set (0.00 sec)

mysql> SELECT * FROM test WHERE MATCH('one three');
Empty set (0.00 sec)
```

To limit matching to a given field, we must use a **field limit operator**, and
`@title` is Sphinx syntax for that. There are many more operators than that, see
["Searching: query syntax"](#searching-query-syntax) section.

Now, when **many** documents match, we usually must **rank** them somehow.
Because we want documents that are more **relevant** to our query to come out
first. That's exactly what `WEIGHT()` is all about.

```
mysql> INSERT INTO test (id, title) VALUES (124, 'hello hello hello');
Query OK, 1 row affected (0.00 sec)

mysql> SELECT *, WEIGHT() FROM test WHERE MATCH('hello');
+------+-------------------+---------+------+-----------+
| id   | title             | content | gid  | weight()  |
+------+-------------------+---------+------+-----------+
|  124 | hello hello hello |         |    0 | 10495.105 |
|  123 | hello world       |         |    0 | 10315.066 |
+------+-------------------+---------+------+-----------+
2 rows in set (0.00 sec)
```

The default Sphinx **ranking function** uses just two **ranking signals** per
each field, namely BM15 (a variation of the classic BM25 function), and LCS (aka
Longest Common Subsequence length). Very basically, LCS "ensures" that closer
phrase matches are ranked higher than scattered keywords, and BM15 mixes that
with per-keyword statistics.

This default ranker (called `PROXIMITY_BM15`) is an okay baseline. It is fast
enough, and provides *some* search quality to start with. Sphinx has a few more
**built-in rankers** that *might* either yield better quality (see `SPH04`), or
perform even better (see `BM15`).

However, **proper ranking is much more complicated than just that.** Once you
switch away from super-simple built-in rankers, Sphinx computes **tens** of very
different **(dynamic) text ranking signals** in runtime, per each field. Those
signals can then be used in either a **custom ranking formula**, or (better yet)
passed to an external **UDF (user-defined function)** that, these days, usually
uses an ML trained model to compute the final weight.

The specific signals (also historically called **factors** in Sphinx lingo) are
covered in the ["Ranking: factors"](#ranking-factors) section. If you know a bit
about ranking in general, have your training corpus and baseline NDCG ready for
immediate action, and you just need to figure out what this little weird Sphinx
system can do specifically, start there. If not, you need a book, and this isn't
that book. **"Introduction to Information Retrieval"** by Christopher Manning is
one excellent option, and freely available online.

Well, that escalated quickly! Before the Abyss of the Dreaded Ranking starts
staring back at us, let's get back to easier, more everyday topics.


### SphinxQL vs regular SQL

Our SphinxQL examples so far looked almost like regular SQL. Yes, there already
were a few syntax extensions like `FIELD` or `MATCH()`, but overall it looked
deceptively SQL-ish, now didn't.

Only, there **are** several very important SphinxQL `SELECT` differences that
should be mentioned early.

**SphinxQL `SELECT` always has an implicit `ORDER BY` and `LIMIT` clauses**,
those are `ORDER BY WEIGHT() DESC, id ASC LIMIT 20` specifically. So by default
you get "top-20 most relevant rows", and that is very much *unlike* regular SQL,
which would give you "all the matching rows in pseudo-random order" instead.

`WEIGHT()` is just always 1 when there's no `MATCH()`, so you get "top-20 rows
with the smallest IDs" that way. `SELECT id, price FROM products` does actually
mean `SELECT id, price FROM products ORDER BY id ASC LIMIT 20` in Sphinx.

You can raise `LIMIT` much higher, but *some* limit is always there, refer to
["Searching: memory budgets"](#searching-memory-budgets) for details.

Next thing, **`WHERE` conditions are a bit limited, but there's a workaround.**
`WHERE` does ***not*** support arbitrary expressions. You can use `MATCH()`, you
can use several types of column value checks, and you **must** `AND` all those
together.

For example, `WHERE MATCH('pencil') AND price < 10` is legal. Bit more complex
`WHERE MATCH('pencil') AND (color = 'red' OR color = 'green')` suddenly is not,
because the `color` part is now an expression, and *not* a simple equality or
range check (like the `price < 100` part was). Again quite unlike regular SQL
that would just support all that.

Good news, there's always a workaround. **Column checks accept expressions.**
In other words, **you can always move expressions to SELECT list**, rewriting
your queries like so.

```sql
SELECT id, (color = 'red' OR color = 'green') AS mycond1 FROM products
WHERE MATCH('pencil') AND mycond1 = 1
```

Suddenly *that* is legal again. Awkward, but legal. But why make it so awkward?!

All `WHERE` conditions boil down to either `WHERE MATCH(...) AND (...)` or to
`WHERE MATCH(...) OR (...)` and these are two very different groups.

The example just above is from the first group, MATCH-AND-condition. Feasible,
but not easy. Every single manual rewrite is literally 100x if not 1000x easier
than getting this *properly* done on Sphinx side. So we also do manual rewrites.
(Yep, cobbler's children, guilty as charged.) But we don't love the resulting
awkward syntax either. One shiny day we should fix it.

Then there's the second MATCH-OR-condition group. Even less feasible, because
full-text and non-text matches are *way* too different internally. Technically,
queries with conditions like `WHERE MATCH('pencil') OR color = 'black'` can be
broken into two queries on the client side, one with the `MATCH()`, and another
one with everything else. But semantically, they barely make sense. Our advice
here is, review them with extreme prejudice.

Last but not least, that *specific* example from above can be rewritten in yet
another way, as `WHERE MATCH('pencil') AND color IN ('red', 'green')`, so with
an `IN()` check instead of expression now. That's also legal.

**So what "column checks" exactly can one use in `WHERE`?** Basically, you can
check how any column's value relates to a given **constant** value (or values). 
The basic math comparison operators (`=`, `!=`, `<`, `>`, `<=`, `>=`) are all
supported. `BETWEEN` and `IN` and `NOT IN` are supported too. Comparisons even
work on sets(ie. `UINT_SET` or `BIGINT_SET`), but you must specify a predicate,
eg. `WHERE ANY(tags) = 123` or `WHERE ALL(tags) != 456`. Finally, for JSON keys,
you can check for theirs existence with `IS NULL` and `IS NOT NULL` operators.
That's generally it.

To reiterate, **columns can also be computed expressions**. The only requirement
is to **give them a unique alias**, and then you can use that in `WHERE` along
with any other static index columns.

```sql
SELECT id, price * 0.9 AS discounted_price FROM products
WHERE color = 'red' AND discount_price < 10
```

**JSON keys can be used in `WHERE` checks with an explicit numeric type cast.**
Sphinx does not support `WHERE j.price < 10`, basically because it does not
generally support `NULL` values. However, `WHERE UINT(j.price) < 10` works fine,
once you provide an explicit numeric type cast (ie. to `UINT`, `BIGINT`, or
`FLOAT` types). Missing or incompatibly typed JSON values cast to zero.

**JSON keys can be checked for existence.** `WHERE j.foo IS NULL` condition
works okay. As expected, it accepts rows that do *not* have a `foo` key in their
JSON `j` column.

Next thing, **aliases in `SELECT` list can be immediately used in the list**,
meaning that `SELECT id + 10 AS a, a * 2 AS b, b < 1000 AS cond` are perfectly
legal. Again unlike regular SQL, but this time SphinxQL is better!

```sql
# this is MySQL
mysql> SELECT id + 10 AS a, a * 2 AS b, b < 1000 AS cond FROM test;
ERROR 1054 (42S22): Unknown column 'a' in 'field list'

# this is Sphinx
mysql> SELECT id + 10 AS a, a * 2 AS b, b < 1000 AS cond FROM test;
+------+------+------+
| a    | b    | cond |
+------+------+------+
|  133 |  266 |    1 |
+------+------+------+
1 row in set (0.00 sec)
```


### Writing your first config

Using a config file and indexing an existing SQL database is also actually
rather simple. Of course, nothing beats the simplicity of "just run `searchd`",
but we will literally need just 3 extra commands using 2 bundled example files.
Let's step through that.

First step is the same, just download and extract Sphinx.
```
$ wget -q https://sphinxsearch.com/files/sphinx-3.6.1-c9dbeda-linux-amd64.tar.gz
$ tar zxf sphinx-3.6.1-c9dbeda-linux-amd64.tar.gz
$ cd sphinx-3.6.1/
```

Second step, populate a tiny test MySQL database from `example.sql`, then run
`indexer` to index that database. (You should, of course, have MySQL or MariaDB
server installed at this point.)
```
$ mysql -u test < ./etc/example.sql
$ ./bin/indexer --config ./etc/sphinx-min.conf.dist --all
Sphinx 3.6.1 (commit c9dbedab)
Copyright (c) 2001-2023, Andrew Aksyonoff
Copyright (c) 2008-2016, Sphinx Technologies Inc (http://sphinxsearch.com)

using config file './etc/sphinx-min.conf.dist'...
indexing index 'test1'...
collected 4 docs, 0.0 MB
sorted 0.0 Mhits, 100.0% done
total 4 docs, 0.2 Kb
total 0.0 sec, 17.1 Kb/sec, 354 docs/sec
skipping non-plain index 'testrt'...
```

Third and final step is also the same, run `searchd` (now with config!) and
query it.
```
$ ./bin/searchd --config ./etc/sphinx-min.conf.dist
Sphinx 3.6.1 (commit c9dbedab)
Copyright (c) 2001-2023, Andrew Aksyonoff
Copyright (c) 2008-2016, Sphinx Technologies Inc (http://sphinxsearch.com)

using config file './etc/sphinx-min.conf.dist'...
listening on all interfaces, port=9312
listening on all interfaces, port=9306
loading 2 indexes...
loaded 2 indexes using 2 threads in 0.0 sec
```
```
$ mysql -h0 -P9306
Welcome to the MariaDB monitor.  Commands end with ; or \g.
Your MySQL connection id is 1
Server version: 3.6.1 (commit c9dbedab)

Copyright (c) 2000, 2018, Oracle, MariaDB Corporation Ab and others.

Type 'help;' or '\h' for help. Type '\c' to clear the current input statement.

mysql> show tables;
+--------+-------+
| Index  | Type  |
+--------+-------+
| test1  | local |
| testrt | rt    |
+--------+-------+
2 rows in set (0.000 sec)

mysql> select * from test1;
+------+----------+------------+
| id   | group_id | date_added |
+------+----------+------------+
|    1 |        1 | 1711019614 |
|    2 |        1 | 1711019614 |
|    3 |        2 | 1711019614 |
|    4 |        2 | 1711019614 |
+------+----------+------------+
4 rows in set (0.000 sec)
```

What just happened? And why jump through all these extra hoops?

So examples before were all based on the **config-less mode**, where `searchd`
stores all the data and settings in a `./sphinxdata` data folder, and you have
to manage everything via `searchd` itself. Neither `indexer` nor any config file
were really involved. That's a perfectly viable operational mode.

However, having a config file with a few general server-wide settings still is
convenient, even if you only use `searchd`. Also, importing data with `indexer`
*requires* a config file. Time to cover that other operational mode.

But first, let's briefly talk about that `./sphinxdata` folder. More formally,
**Sphinx requires a datadir, ie. a folder to store all its data and settings**,
and `./sphinxdata` is just a default path for that. For a detailed discussion,
see ["Using datadir"](#using-datadir) section. For now, let's just mention that
a non-default datadir can be set either from config, or from the command line.

```bash
$ searchd --datadir /home/sphinx/sphinxdata
```

**Config file location can be changed from the command line too.** The default
location is `./sphinx.conf` but all Sphinx programs take the `--config` switch.

```bash
$ indexer --config /home/sphinx/etc/indexer.conf
```

**Config file lets you control both global settings, and individual indexes.**
Datadir path is a prominent global setting, but just one of them, and there are
**many** more.

For example,`max_children`, the server-wide worker threads limit that helps
prevents `searchd` from being terminally overloaded. Or `lemmatizer_base`, path
to lemmatization dictionaries. Or `auth_users`, the secret users/hashes file
that can restrict `searchd` access. The complete lists can be found in their
respective sections.

  * [Common config reference](#common-config-reference)
  * [`indexer` config reference](#indexer-config-reference)
  * [`searchd` config reference](#searchd-config-reference)

**Some settings can intentionally ONLY be enabled via config.** For instance,
`auth_users` or `json_float` **MUST** be configured that way. We don't plan to
change those on the fly.

But perhaps even more importantly...

**Indexing pre-existing data with `indexer` requires a config file** that
specifies the **data sources** to get the raw data from, and sets up the target
full-text index to put the indexed data to. Let's open `sphinx-min.conf.dist`
and see for ourselves.

```bash
source src1
{
    type        = mysql

    sql_host    = localhost # for `sql_port` to work, use 127.0.0.1
    sql_user    = test
    sql_pass    =
    sql_db      = test
    sql_port    = 3306  # optional, default is 3306

    # use `example.sql` to populate `test.documents` table
    sql_query   = SELECT id, group_id, UNIX_TIMESTAMP(date_added)
                  AS date_added, title, content FROM documents
}
```

This **data source configuration** tells `indexer` what database to connect to,
and what SQL query to run. **Arbitrary SQL queries can be used here**, as Sphinx
does not limit that SQL anyhow. You can `JOIN` multiple tables in your `SELECT`,
or call stored procedures instead. Anything works, as long as it talks SQL and
returns some result set that Sphinx can index. That covers the raw input data.

**Native database drivers** currently exist for **MySQL, PostgreSQL, and ODBC**
(so MS SQL or Oracle or anything else with an ODBC driver also works). Bit more
on that in the ["Indexing: data sources"](#indexing-data-sources) section.

Or you can pass your data to `indexer` in **CSV, TSV, or XML formats**. Details
in the ("Indexing: CSV and TSV files")[#indexing-csv-and-tsv-files] section.

Then the **full-text index configuration** tells `indexer` what data sources to
index, and what specific settings to use. Index type and schema are mandatory.
For the so-called "plain" indexes that `indexer` works with, a list of data
sources is mandatory too. Let's see.

```bash
index test1
{
    type        = plain
    source      = src1

    field       = title, content
    attr_uint   = group_id, date_added
}
```

That's it. Now the `indexer` knows that to build an index called `test1` it must
take the input data from `src1` source, index the 2 input columns as text fields
(`title` and `content`), store the 3 input columns as attributes, and that's it.

Not a typo, 3 (three) columns. There must **always** be a unique document ID, so
on top of the 2 explicit `group_id` and `date_added` attributes, we always have
another 1 called `id`. We already saw it earlier.

```sql
mysql> select * from test1;
+------+----------+------------+
| id   | group_id | date_added |
+------+----------+------------+
|    1 |        1 | 1711019614 |
|    2 |        1 | 1711019614 |
|    3 |        2 | 1711019614 |
|    4 |        2 | 1711019614 |
+------+----------+------------+
4 rows in set (0.000 sec)
```

Another important thing is **the index type**, that's the `type = plain` line in
our example. Two base full-text index types are the so-called **RT indexes** and
**plain indexes**, and `indexer` creates the "plain" ones at the moment.

**Plain indexes are limited compared to "proper" RT indexes**, and the biggest
difference is that **you can't really modify any *full-text* data they store**.
You can still run `UPDATE` and `DELETE` queries, even on plain indexes. But you
can *not* `INSERT` any new full-text searchable data. However, when needed, you
also "convert" a plain index to an RT index with an `ATTACH` statement, and then
run `INSERT` queries on that.

**The only way to add rows to a plain index is to fully rebuild it** by running
`indexer`, but fear not, existing plain indexes served by `searchd` will *not*
suddenly stop working once you run `indexer`! It will create a temporary shadow
copy of the specified index(es), rebuild them offline, and then sends a signal
to `searchd` to pick up those newly rebuilt shadow copies.

**Index schema is a list of index fields and attributes.** More details are in
the ("Using index schemas")[#using-index-schemas] section.

Note how the MySQL query column order in `sql_query` and the index schema order
are different, and how `UNIX_TIMESTAMP(date_added)` was aliased. That's because
**source columns are bound to index schema by name**, and the names must match.
Sometimes you can configure Sphinx index columns to perfectly match SQL table
columns, and then the simplest `sql_query = SELECT * ...` works, but usually
it's easier to alias `sql_query` columns as needed for Sphinx.

**The very first `sql_query` must be the document ID. Its name gets ignored.**
That's the only exception from the "names must match" rule.

Also, **document IDs must be unique 64-bit signed integers.** For the record,
Sphinx does not *need* those IDs itself, they really are for *you* to uniquely
identify the rows stored in Sphinx, and (optionally) to cross-reference them
with your other databases. That works well for most applications: one usually
does have a PK, and that PK is frequently an `INT` or `BIGINT` anyway! When your
existing IDs do not easily convert to integer (eg. GUIDs), you can hash them or
generate sequences in your `sql_query` and generate Sphinx-only IDs that way.
Just make sure they're unique.

As a side note, in early 2024 MySQL still does not seem to support sequences.
See how that works in PostgreSQL. (In MySQL you could probably emulate that with
counter variables or recursive CTEs.)

```sql
postgres=# CREATE TEMPORARY SEQUENCE testseq START 123;
CREATE SEQUENCE
postgres=# SELECT NEXTVAL('testseq'), * FROM test;
 nextval |       title
---------+--------------------
     123 | hello world
     124 | document two
     125 | third time a charm
(3 rows)
```

The ideal place for that `CREATE SEQUENCE` statement would be `sql_query_pre`
and that segues us into config settings (we tend to call them **directives** in
Sphinx). Well, there are quite a few, and they are useful.

See ["Source config reference"](#source-config-reference) for all the source
level ones. Sources are basically all about getting the input data. So their
directives let you flexibly configure all that jazz (SQL access, SQL queries,
CSV headers, etc).

See ["Index config reference](#index-config-reference) for all the index level
directives. They are more diverse, but **text processing directives** are worth
a quick early mention here.

**Sphinx has a lot of settings that control full-text indexing and searching.**
Flexible tokenization, morphology, mappings, annotations, mixed codes, tunable
HTML stripping, in-field zones, we got all that and more.

Eventually, there must be a special nice chapter *explaining* all that. Alas,
right now, there isn't. But some of the features are already covered in their
respective sections.

  * [Using annotations](#using-annotations), short "phrases" all stored into one
    text field that can be matched and ranked individually
  * [Using mappings](#using-mappings), text processing stage that lets you map
    keywords to keywords (either 1:1 or M:N, either before or after morphology)
  * [Using morphdict](#using-morphdict), custom 1:1 overrides for morphology
    (stemming or lemmatization) processors
  * [Special chars, blending tokens, and mixed codes][2]: tools for processing
    magic tokens like `C++`, or `@elonmusk`, or `QN65S95DAFXZ`
  
And, of course, *all* the directives are always *documented* in the index config
reference.

To wrap up dissecting our example `sphinx-min.conf.dist` config, let's look at
its last few lines.

```bash
index testrt
{
    type        = rt

    field       = title, content
    attr_uint   = gid
}
```

**Config file also lets you create RT indexes. ONCE.** That `index testrt`
section is *completely* equivalent to this statement.

```sql
CREATE TABLE IF NOT EXISTS testrt
(id bigint, title field, content field, uint)
```

Note that the **RT index definition from the config only applies ONCE**, when
you (re)start `searchd` with that new definition for the very first time. It is
**not enough** to simply change the config definition in the config, `searchd`
will **not** automatically apply those changes. Instead, it will warn about the
differences. For example, if we change the attrs to `attr_uint = gid, gid2` and
restart, we get this warning.

```bash
$ ./bin/searchd -c ./etc/sphinx-min.conf.dist
...
WARNING: index 'testrt': attribute count mismatch (3 in config, 2 in header);
EXISTING INDEX TAKES PRECEDENCE
```

And the schema stays unchanged.

```sql
mysql> desc testrt;
+---------+--------+------------+------+
| Field   | Type   | Properties | Key  |
+---------+--------+------------+------+
| id      | bigint |            |      |
| title   | field  | indexed    |      |
| content | field  | indexed    |      |
| gid     | uint   |            |      |
+---------+--------+------------+------+
4 rows in set (0.00 sec)
```

To add the new column, we need to either recreate that index, or use the `ALTER`
statement.

So what's better for RT indexes, `sphinx.conf` definitions or `CREATE TABLE`
statements? Both approaches are now viable. (Historically, `CREATE TABLE` did
not support *all* the directives that configs files did, but today it supports
almost everything.) So we have **two different schema management approaches**,
with their own pros and contras. Pick one to your own taste, or even use both
approaches for different indexes. Whatever works best!


### Running queries from PHP, Python, etc

```php
<?php

$conn = mysqli_connect("127.0.0.1:9306", "", "", "");
if (mysqli_connect_errno())
    die("failed to connect to Sphinx: " . mysqli_connect_error());

$res = mysqli_query($conn, "SHOW VARIABLES");
while ($row = mysqli_fetch_row($res))
    print "$row[0]: $row[1]\n";
```

```python
import pymysql

conn = pymysql.connect(host="127.0.0.1", port=9306)
cur = conn.cursor()
cur.execute("SHOW VARIABLES")
rows = cur.fetchall()

for row in rows:
    print(row)
```

TODO: examples

### Running queries via HTTP

TODO: examples

### Installing SQL drivers

This only affects `indexer` ETL tool only. If you never ever bulk load data from
SQL sources that may require drivers, you can safely skip this section. (Also,
if you are on Windows, then all the drivers are bundled, so also skip.)

Depending on your OS, the required package names may vary. Here are some current
(as of Mar 2018) package names for Ubuntu and CentOS:

```bash
ubuntu$ apt-get install libmysqlclient-dev libpq-dev unixodbc-dev
ubuntu$ apt-get install libmariadb-client-lgpl-dev-compat

centos$ yum install mariadb-devel postgresql-devel unixODBC-devel
```

Why might these be needed, and how they work?

`indexer` natively supports MySQL (and MariaDB, and anything else wire-protocol
compatible), PostgreSQL, and UnixODBC drivers. Meaning it can natively connect
to those databases, run SQL queries, extract results, and create full-text
indexes from that. Sphinx binaries now always come with that *support* enabled.

However, you still need to have a specific driver *library* installed on your
system, so that `indexer` could dynamically load it, and access the database.
Depending on the specific database and OS you use, the package names might be
different, as you can see just above.

The driver libraries are loaded by name. The following names are tried:

  * MySQL: `libmysqlclient.so` and `libmariadb.so`
  * PostgreSQL: `libpq.so`
  * ODBC: `libodbc.so`

To support MacOS, `.dylib` extension (in addition to `.so`) is also tried.

Last but not least, if a specific package that you use on your specific OS fails
to properly install a driver, you might need to create a link manually.

For instance, we have seen a package install `libmysqlclient.so.19` alright, but
fail to create a generic `libmysqlclient.so` link for whatever reason. Sphinx
could not find that, because that extra `.19` is an internal *driver* version,
specific (and known) only to the driver, not us! A mere `libmysqlclient.so`
symlink fixed that. Fortunately, most packages create the link themselves.


Main concepts
--------------

Alas, many projects tend to reinvent their own dictionary, and Sphinx is
no exception. Sometimes that probably creates confusion for no apparent reason.
For one, what SQL guys call "tables" (or even "relations" if they are old enough
to remember Edgar Codd), and MongoDB guys call "collections", we the text search
guys tend to call "indexes", and not really out of mischief and malice either,
but just because for us, those things *are* primarily FT (full-text) indexes.
Thankfully, most of the concepts are close enough, so our personal little Sphinx
dictionary is tiny. Let's see.

Short cheat sheet!

| Sphinx             | Closest SQL equivalent                    |
|--------------------|-------------------------------------------|
| Index              | Table                                     |
| Document           | Row                                       |
| Field or attribute | Column and/or a full-text index           |
| Indexed field      | *Just* a full-text index on a text column |
| Stored field       | Text column *and* a full-text index on it |
| Attribute          | Column                                    |
| MVA                | Column with an INT_SET type               |
| JSON attribute     | Column with a JSON type                   |
| Attribute index    | Index                                     |
| Document ID, docid | Column called "id", with a BIGINT type    |
| Row ID, rowid      | Internal Sphinx row number                |
| Schema             | A list of columns                         |

And now for a little more elaborate explanation.

### Indexes

Sphinx indexes are semi-structured collections of documents. They may seem
closer to SQL tables than to Mongo collections, but in their core, they really
are neither. The primary, foundational data structure here is a *full-text
index*. It is a special structure that lets us respond very quickly to a query
like "give me the (internal) identifiers of all the documents that mention This
or That keyword". And everything else (any extra attributes, or document
storage, or even the SQL or HTTP querying dialects, and so on) that Sphinx
provides is essentially some kind of an addition on top of that base data
structure. Well, hence the "index" name.

Schema-wise, Sphinx indexes try to combine the best of schemaful and schemaless
worlds. For "columns" where you know the type upfront, you can use the
statically typed attributes, and get the absolute efficiency. For more dynamic
data, you can put it all into a JSON attribute, and still get quite decent
performance.

So in a sense, Sphinx indexes == SQL tables, except (a) full-text searches are
fast and come with a lot of full-text-search specific tweaking options; (b) JSON
"columns" (attributes) are quite natively supported, so you can go schemaless;
and (c) for full-text indexed fields, you can choose to store *just* the
full-text index and ditch the original values.

### Documents

Documents are essentially just a list of named text fields, and arbitrary-typed
attributes. Quite similar to SQL rows; almost indistinguishable, actually.

As of v.3.0.1, Sphinx still requires a unique `id` attribute, and implicitly
injects an `id BIGINT` column into indexes (as you probably noticed in the
[Getting started](#getting-started) section). We still use those docids to
identify specific rows in `DELETE` and other statements. However, unlike in
v.2.x, we no longer use docids to identify documents internally. Thus, zero and
negative docids are already allowed.

### Fields

Fields are the texts that Sphinx indexes and makes keyword-searchable. They
always are *indexed*, as in full-text indexed. Their original, unindexed
contents can also be *stored* into the index for later retrieval. By default,
they are not, and Sphinx is going to return attributes only, and *not* the
contents. However, if you explicitly mark them as stored (either with
a `stored` flag in `CREATE TABLE` or in the ETL config file using
`stored_fields` directive), you can also fetch the fields back:

```sql
mysql> CREATE TABLE test1 (title field);
mysql> INSERT INTO test1 VALUES (123, 'hello');
mysql> SELECT * FROM test1 WHERE MATCH('hello');
+------+
| id   |
+------+
|  123 |
+------+
1 row in set (0.00 sec)

mysql> CREATE TABLE test2 (title field stored);
mysql> INSERT INTO test2 VALUES (123, 'hello');
mysql> SELECT * FROM test2 WHERE MATCH('hello');
+------+-------+
| id   | title |
+------+-------+
|  123 | hello |
+------+-------+
1 row in set (0.00 sec)
```

Stored fields contents are stored in a special index component called document
storage, or DocStore for short.

### Attributes

Sphinx supports the following attribute types:

  * UINT, unsigned 32-bit integer
  * BIGINT, signed 64-bit integer
  * FLOAT, 32-bit (single precision) floating point
  * BOOL, 1-bit boolean
  * STRING, a text string
  * BLOB, a binary string
  * JSON, a JSON document
  * UINT_SET aka MVA, an order-insensitive set of unique UINTs
  * BIGINT_SET aka MVA64, an order-insensitive set of unique BIGINTs
  * INT_ARRAY, fixed-width array of signed 32-bit integers
  * INT8_ARRAY, fixed-width array of signed 8-bit integers
  * FLOAT_ARRAY, fixed-width array of 32-bit floats

All of these should be pretty straightforward. However, there are a couple
Sphinx specific JSON performance tricks worth mentioning:

  * All scalar values (integers, floats, doubles) are converted and internally
    stored natively.
  * All scalar value *arrays* are detected and also internally stored natively.
  * You can use `123.45f` syntax extension to mark 32-bit floats (by default all
    floating point values in JSON are 64-bit doubles).

For example, when the following document is stored into a JSON column in Sphinx:
```json
{"title":"test", "year":2017, "tags":[13,8,5,1,2,3]}
```
Sphinx detects that the "tags" array consists of integers only, and stores the
array data using 24 bytes exactly, using just 4 bytes per each of the 6 values.
Of course, there still are the overheads of storing the JSON keys, and the
general document structure, so the *entire* document will take more than that.
Still, when it comes to storing bulk data into Sphinx index for later use, just
provide a consistently typed JSON array, and that data will be stored - and
processed! - with maximum efficiency.

Attributes are supposed to fit into RAM, and Sphinx is optimized towards that
case. Ideally, of course, all your index data should fit into RAM, while being
backed by a fast enough SSD for persistence.

Now, there are *fixed-width* and *variable-width* attributes among the
supported types. Naturally, scalars like `UINT` and `FLOAT` will always occupy
exactly 4 bytes each, while `STRING` and `JSON` types can be as short as, well,
empty; or as long as several megabytes. How does that work internally? Or in
other words, why don't I just save everything as JSON?

The answer is performance. Internally, Sphinx has two separate storages for
those row parts. Fixed-width attributes, including hidden system ones, are
essentially stored in big static NxM matrix, where N is the number of rows, and
M is the number of fixed-width attributes. Any accesses to those are very quick.
All the variable-width attributes for a single row are grouped together, and
stored in a separate storage. A single offset into that second storage (or
"vrow" storage, short for "variable-width row part" storage) is stored as hidden
fixed-width attribute. Thus, as you see, accessing a string or a JSON or an MVA
value, let alone a JSON key, is somewhat more complicated. For example, to
access that `year` JSON key from the example just above, Sphinx would need to:

  * read `vrow_offset` from a hidden attribute
  * access the vrow part using that offset
  * decode the vrow, and find the needed JSON attribute start
  * decode the JSON, and find the `year` key start
  * check the key type, just in case it needs conversion to integer
  * finally, read the `year` value

Of course, optimizations are done on every step here, but still, if you access
a *lot* of those values (for sorting or filtering the query results), there will
be a performance impact. Also, the deeper the key is buried into that JSON, the
worse. For example, using a tiny test with 1,000,000 rows and just 4 integer
attributes plus exactly the same 4 values stored in a JSON, computing a sum
yields the following:

| Attribute    | Time      | Slowdown  |
|--------------|-----------|-----------|
| Any UINT     | 0.032 sec | -         |
| 1st JSON key | 0.045 sec | 1.4x      |
| 2nd JSON key | 0.052 sec | 1.6x      |
| 3rd JSON key | 0.059 sec | 1.8x      |
| 4th JSON key | 0.065 sec | 2.0x      |

And with more attributes it would eventually slowdown even worse than 2x times,
especially if we also throw in more complicated attributes, like strings or
nested objects.

So bottom line, why not JSON everything? As long as your queries only touch
a handful of rows each, that is fine, actually! However, if you have a *lot* of
data, you should try to identify some of the "busiest" columns for your queries,
and store them as "regular" typed columns, that somewhat improves performance.

### Schemas

**Schema is an (ordered) list of columns (fields and attributes).** Sounds easy.
Except that "column lists" quite naturally turn up in quite a number of places,
and in every specific place, there just might be a few specific quirks.

**There usually are multiple different schemas at play.** Even "within" a single
index or query!

Obviously, there always has to be some **index schema**, the one that defines
all the index fields and attributes. Or in other words, it defines the structure
of the indexed documents, so calling it **(index) document schema** would also
be okay.

Most SELECTs need to grab a custom list of columns and/or expressions, so then
there always is a **result set schema** with that. And, coming from the query,
it differs from the index schema.

As a side note for the really curious *and* also for ourselves the developers,
internally there very frequently is yet another intermediate "sorter" schema,
which differs again. For example, consider an `AVG(col)` expression. The index
schema does not even have that. The final result set schema must only return one
(float) value. But we have to store two values (the sum and the row counter)
while *processing* the rows. The intermediate schemas take care of differences
like that.

Back to user facing queries, INSERTs can also take an explicit list of columns,
and guess what, that is an **insert schema** right there.

Thankfully, as engine users we mostly only need to care about the index schemas.
We discuss those in detail just below.


Using index schemas
--------------------

Just like SQL tables must have at least *some* columns in them, Sphinx indexes
*must* have at least 1 full-text indexed field declared by you, the user. Also,
there *must* be at least 1 attribute called `id` with the document ID. That one
does not need to be declared, as the system adds it automatically. So the most
basic "table" (aka index) **always has at least two "columns" in Sphinx**:
the system `id`, and the mandatory user field. For example, `id` and `title`,
or however else you name your field.

Of course, **you can define somewhat more fields and attributes than that!**
For a running example, one still on the simple side, let's say that we want just
a couple of fields, called `title` and `content`, and a few more attributes,
say `user_id`, `thread_id`, and `post_ts` (hmm, looks like forum messages).

Now, this set of fields and attributes is called a **schema** and it affects
a number of not unimportant things. What columns does `indexer` expect from its
data sources? What's the default column order as returned by `SELECT` queries?
What's the order expected by `INSERT` queries without an explicit column list?
And so on.

So this section discusses everything about the schemas. How exactly to define
them, examine them, change them, and whatnot. And, rather importantly, what are
the Sphinx specific quirks.

### Schemas: index config

**All fields and attributes must be declared upfront** for both plain and RT
indexes in their configs. **Fields go first** (using `field` or `field_string`
directives), and **attributes go next** (using `attr_xxx` directives, where
`xxx` picks a proper type). Like so.

```bash
index ex1
{
    type = plain
    field = title, content
    attr_bigint = user_id, thread_id
    attr_uint = post_ts
}
```

**Sphinx automatically enforces the document ID column.** The type is `BIGINT`,
the values must be unique, and the column always is the very first one. Ah, and
`id` is the only attribute that does not ever have to be explicitly declared.

That summarizes to **"ID leads, then fields first, then attributes next"** as
our general rule of thumb for column order. Sphinx enforces that rule everywhere
where some kind of a *default* column order is needed.

The "ID/fields/attributes" rule affects the config declaration order too. Simply
to keep what you put in the config in sync with what you get from `SELECT` and
`INSERT` queries (at least by default).

**Here's the list of specific `attr_xxx` types.** Or, you can also refer to
the ["Index config reference"](#index-config-reference) section. (Spoiler: that
list is checked automatically; this one is checked manually.)

| Directive          | Type description                       |
|--------------------|----------------------------------------|
| `attr_bigint`      | signed 64-bit integer                  |
| `attr_bigint_set`  | a sorted set of signed 64-bit integers |
| `attr_blob`        | binary blob (embedded zeroes allowed)  |
| `attr_bool`        | 1-bit boolean value, 1 or 0            |
| `attr_float`       | 32-bit float                           |
| `attr_float_array` | an array of 32-bit floats              |
| `attr_int_array`   | an array of 32-bit signed integers     |
| `attr_int8_array`  | an array of 8-bit signed integers      |
| `attr_json`        | JSON object                            |
| `attr_string`      | text string (zero terminated)          |
| `attr_uint`        | unsigned 32-bit integer                |
| `attr_uint_set`    | a sorted set of signed 32-bit integers |

**For array types, you must also declare the array dimensions.** You specify
those just after the column name, like so.

```bash
attr_float_array = vec1[3], vec2[5]
```

**You can use either lists, or individual entries** with those directives.
The following one-column-per-line variation works identically fine.

```bash
index ex1a
{
    type = rt
    field = title
    field = content
    attr_bigint = user_id
    attr_bigint = thread_id
    attr_uint = post_ts
}
```

**The resulting index schema order must match the config order.** Meaning that
the default `DESCRIBE` and `SELECT` columns order should **exactly** match your
config declaration. Let's check and see!

```sql
mysql> desc ex1a;
+-----------+--------+------------+------+
| Field     | Type   | Properties | Key  |
+-----------+--------+------------+------+
| id        | bigint |            |      |
| title     | field  | indexed    |      |
| content   | field  | indexed    |      |
| user_id   | bigint |            |      |
| thread_id | bigint |            |      |
| post_ts   | uint   |            |      |
+-----------+--------+------------+------+
6 rows in set (0.00 sec)

mysql> insert into ex1a values (123, 'hello world',
    -> 'some content', 456, 789, 1234567890);
Query OK, 1 row affected (0.00 sec)

mysql> select * from ex1a where match('@title hello');
+------+---------+-----------+------------+
| id   | user_id | thread_id | post_ts    |
+------+---------+-----------+------------+
|  123 |     456 |       789 | 1234567890 |
+------+---------+-----------+------------+
1 row in set (0.00 sec)
```

**Fields from `field_string` are "auto-copied" as string attributes** that have
the same names as the original fields. As for the order, the copied attributes
columns sit between the fields and the "regular" explicitly declared attributes.
For instance, what if we declare `title` using `field_string`?

```bash
index ex1b
{
    type = rt
    field_string = title
    field = content
    attr_bigint = user_id
    attr_bigint = thread_id
    attr_uint = post_ts
}
```

Compared to `ex1a` we would expect a single extra string attribute just *before*
`user_id` and that is indeed what we get.

```sql
mysql> desc ex1b;
+-----------+--------+------------+------+
| Field     | Type   | Properties | Key  |
+-----------+--------+------------+------+
| id        | bigint |            |      |
| title     | field  | indexed    |      |
| content   | field  | indexed    |      |
| title     | string |            |      |
| user_id   | bigint |            |      |
| thread_id | bigint |            |      |
| post_ts   | uint   |            |      |
+-----------+--------+------------+------+
7 rows in set (0.00 sec)
```

This kinda reiterates our **"fields first, attributes next"** rule of thumb.
Fields go first, attributes go next, and even in the attributes list, fields
copies go first again. Which brings us to the next order of business.

**Column names must be unique, across both fields and attributes.** Attempts to
*explicitly* use the same name twice for a field and an attribute must now fail.

```bash
index ex1c
{
    type = rt
    field_string = title
    field = content
    attr_bigint = user_id
    attr_bigint = thread_id
    attr_uint = post_ts
    attr_string = title # <== THE OFFENDER
}
```

That fails with the `duplicate attribute name 'title'; NOT SERVING` message,
because we attempt to *explicitly* redeclare `title` here. The proper way is to
use `field_string` directive instead.

**Schemas either inherit fully, or reset completely.** Meaning, when the index
settings are inherited from a parent index (as in `index child : index base`),
the parent schema initially gets inherited too. However, if the child index then
uses any of the fields or attributes directives, the parent schema is discarded
immediately and completely, and only the new directives take effect. So you must
either inherit and use the parent index schema unchanged, or fully define a new
one from scratch. Somehow "extending" the parent schema is not (yet) allowed.

Last but not least, **config column order controls the (default) query order**,
more on that below.

### Schemas: CREATE TABLE

**Columns in CREATE TABLE must also follow the id/fields/attrs rule.** You must
specify a leading `id BIGINT` at all times, and then at least one field. Then
any other fields and attributes can follow. Our running example translates to
SQL as follows.

```sql
CREATE TABLE ex1d (
    id BIGINT,
    title FIELD_STRING,
    content FIELD,
    user_id BIGINT,
    thread_id BIGINT,
    post_ts UINT);
```

The resulting `ex1d` full-text index should be identical to `ex1c` created
earlier via the config.

### Schemas: query order

`SELECT` and `INSERT` (and its `REPLACE` variation) base their column order on
the schema order in absence of an explicit query one, that is, in the `SELECT *`
case and the `INSERT INTO myindex VALUES (...)` case, respectively. For both
implementation and performance reasons those orders need to differ a bit from
the config one. Let's discuss that.

The star expansion order in `SELECT` is:

  - `id` first;
  - all available fields next, in config order;
  - all attributes next, in config order.

The "ID/fields/attributes" motif continues here, but here's the catch, Sphinx
does not always store the original field *contents* when indexing. You have to
explicitly request that with either `field_string` or `stored_fields` and have
the content stored either as an attribute or into DocStore respectively. Unless
you do that, the original field content is *not* available, and `SELECT` can not
and does not return it. Hence the "available" part in the wording.

Now, the default `INSERT` values order should match the enforced config order
completely, and the "ID/fields/attributes" rule applies without the "available"
clause:

  - `id` first;
  - all fields next, in config order;
  - all attributes next, in config order.

Nothing omitted here, naturally. The default incoming document must contain
*all* the known columns, including *all* the fields. You can choose to omit
something explicitly using the `INSERT` column list syntax. But not by default.

Keeping our example running, with this config:

```bash
index ex1b
{
    type = rt
    field_string = title
    field = content
    attr_bigint = user_id
    attr_bigint = thread_id
    attr_uint = post_ts
}
```

We must get the following column sets:

```bash
# SELECT * returns:
id, title, user_id, thread_id, post_ts

# INSERT expects:
id, title, content, user_id, thread_id, post_ts
```

And we do!

```sql
mysql> insert into ex1b values
    -> (123, 'hello world', 'my test content', 111, 222, 333);
Query OK, 1 row affected (0.00 sec)

mysql> select * from ex1b where match('test');
+------+-------------+---------+-----------+---------+
| id   | title       | user_id | thread_id | post_ts |
+------+-------------+---------+-----------+---------+
|  123 | hello world |     111 |       222 |     333 |
+------+-------------+---------+-----------+---------+
1 row in set (0.00 sec)
```

### Schemas: autocomputed attributes

**Any autocomputed attributes should be appended after the user ones.**

Depending on the index settings, Sphinx can compute a few things automatically
and store them as attributes. One notable example is `index_field_lengths` that
adds an extra **autocomputed** length attributes for every field.

The specific order in which Sphinx adds them may vary. For instance, as of time
of this writing, the autocomputed attributes start with index lengths, the token
class masks are placed after the lengths, etc. That **may** change in the future
versions, and you **must not** depend on this specific order.

However, it's guaranteed that all the autocomputed attributes are autoadded
strictly after the user ones, at the very end of the schema.

Also, **autocomputed attributes are "skipped" from INSERTs.** Meaning that you
should not specify them neither explicitly by name, nor implicitly. Even if you
have automatic `title_len` in your index, you only ever have to specify `title`
in your `INSERT` statements, and the `title_len` will be filled automatically.

### Schemas: data sources

**Starting from v.3.6 source-level schemas are deprecated.** You can not mix
them with the new index-level schemas, and you should convert your configs to
index-level schemas ASAP.

Converting is pretty straightforward. It should suffice to:

  1. move the attributes declarations from the source level to index level;
  2. edit out the prefixes (ie. `sql_attr_bigint` becomes `attr_bigint`);
  3. add the explicit fields declarations if needed.

You will also have to move the fields declarations before the attributes.
Putting fields before attributes is an error in the new unified config syntax.

So, for example...

```bash
# was: old source-level config (implicit fields, boring prefixes, crazy and
# less than predictable column order)
source foo
{
    ...
    sql_query = select id, price, lat, lon, title, created_ts FROM mydocs
    sql_attr_float = lat
    sql_attr_float = lon
    sql_attr_bigint = price
    sql_attr_uint = create_ts
}

# now: must move to new index-level config (explicit fields, shorter syntax,
# and columns in the index-defined order, AS THEY MUST BE (who said OCD?!))
source foo
{
    ...
    sql_query = select id, price, lat, lon, title, created_ts FROM mydocs
}

index foo
{
    ...
    source = foo
    
    field = title
    attr_float = lat, lon
    attr_bigint = price
    attr_uint = create_ts
}
```

MVAs (aka integer set attributes) are the only exception that does not convert
using just a simple search/replace (arguably, a simple regexp would suffice).

Legacy `sql_attr_multi = {uint | bigint} <attr> from field` syntax should now be
converted to `attr_uint_set = <attr>` (or `attr_bigint_set` respectively). Still
a simple search/replace, that.

Legacy `sql_attr_multi = {uint | bigint} <attr> from query; SELECT ...` syntax
should now be split to `attr_uint_set = <attr>` declaration at index level, and
`sql_query_set = <attr>: SELECT ...` query at source level.

Here's an example.

```bash
# that was then
# less lines, more mess
source bar
{
    ...
    sql_attr_multi = bigint locations from field
    sql_attr_multi = uint models from query; SELECT id, model_id FROM car2model
}

# this is now
# queries belong in the source, as ever
source bar
{
    ...
    sql_query_set = models: SELECT id, model_id FROM car2model
}

# but attributes belong in the index!
index bar
{
    ...
    attr_bigint_set = locations
    attr_uint_set = models
}
```


Using DocStore
---------------

Storing fields into your indexes is easy, just list those fields in
a `stored_fields` directive and you're all set:

```bash
index mytest
{
    type = rt
    path = data/mytest

    field = title
    field = content
    stored_fields = title, content
    # hl_fields = title, content

    attr_uint = gid
}
```

Let's check how that worked:

```sql
mysql> desc mytest;
+---------+--------+-----------------+------+
| Field   | Type   | Properties      | Key  |
+---------+--------+-----------------+------+
| id      | bigint |                 |      |
| title   | field  | indexed, stored |      |
| content | field  | indexed, stored |      |
| gid     | uint   |                 |      |
+---------+--------+-----------------+------+
4 rows in set (0.00 sec)

mysql> insert into mytest (id, title) values (123, 'hello world');
Query OK, 1 row affected (0.00 sec)

mysql> select * from mytest where match('hello');
+------+------+-------------+---------+
| id   | gid  | title       | content |
+------+------+-------------+---------+
|  123 |    0 | hello world |         |
+------+------+-------------+---------+
1 row in set (0.00 sec)
```

Yay, original document contents! Not a huge step generally, not for a database
anyway; but a nice improvement for Sphinx which was initially designed "for
searching only" (oh, the mistakes of youth). And DocStore can do more than that,
namely:

  * store indexed fields, `store_fields` directive
  * store unindexed fields, `stored_only_fields` directive
  * store precomputed data to speedup snippets, `hl_fields` directive
  * be fine-tuned a little, using `docstore_type`, `docstore_comp`, and
    `docstore_block` directives

So DocStore can effectively replace the existing `attr_string` directive. What
are the differences, and when to use each?

`attr_string` creates an *attribute*, which is uncompressed, and always in RAM.
Attributes are supposed to be small, and suitable for filtering (WHERE), sorting
(ORDER BY), and other operations like that, by the millions. So if you really
need to run queries like `... WHERE title='abc'`, or in case you want to update
those strings on the fly, you will still need attributes.

But complete original document contents are rather rarely accessed in *that*
way! Instead, you usually need just a handful of those, in the order of 10s to
100s, to have them displayed in the final search results, and/or create
snippets. DocStore is designed exactly for that. It compresses all the data it
receives (by default), and tries to keep most of the resulting "archive" on
disk, only fetching a few documents at a time, in the very end.

Snippets become pretty interesting with DocStore. You can generate snippets
from either specific stored fields, or the entire document, or a subdocument,
respectively:

```sql
SELECT id, SNIPPET(title, QUERY()) FROM mytest WHERE MATCH('hello')
SELECT id, SNIPPET(DOCUMENT(), QUERY()) FROM mytest WHERE MATCH('hello')
SELECT id, SNIPPET(DOCUMENT({title}), QUERY()) FROM mytest WHERE MATCH('hello')
```

Using `hl_fields` can accelerate highlighting where possible, sometimes making
snippets *times* faster. If your documents are big enough (as in, a little
bigger than tweets), try it! Without `hl_fields`, SNIPPET() function will have
to reparse the document contents every time. With it, the parsed representation
is compressed and stored into the index upfront, trading off a not-insignificant
amount of CPU work for more disk space, and a few extra disk reads.

And speaking of disk space vs CPU tradeoff, these tweaking knobs let you
fine-tune DocStore for specific indexes:

  * `docstore_type = vblock_solid` (default) groups small documents into
    a single compressed block, up to a given limit: better compression,
    slower access
  * `docstore_type = vblock` stores every document separately: worse
    compression, faster access
  * `docstore_block = 16k` (default) lets you tweak the block size limit
  * `docstore_comp = lz4hc` (default) uses LZ4HC algorithm for compression:
    better compression, but slower
  * `docstore_comp = lz4` uses LZ4 algorithm: worse compression, but faster
  * `docstore_comp = none` disables compression


Using attribute indexes
------------------------

Quick kickoff: we now have [`CREATE INDEX` statement](#create-index-syntax)
which lets you create secondary indexes, and sometimes (or most of times even?!)
it *does* make your queries faster!

```sql
CREATE INDEX i1 ON mytest(group_id)
DESC mytest
SELECT * FROM mytest WHERE group_id=1
SELECT * FROM mytest WHERE group_id BETWEEN 10 and 20
SELECT * FROM mytest WHERE MATCH('hello world') AND group_id=23
DROP INDEX i1 ON mytest
```

Point reads, range reads, and intersections between `MATCH()` and index reads
are all intended to work. Moreover, `GEODIST()` can also automatically use
indexes (see more below). One of the goals is to completely eliminate the need
to insert "fake keywords" into your index. (Also, it's possible to *update*
attribute indexes on the fly, as opposed to indexed text.)

Indexes on JSON keys should also work, but you might need to cast them to
a specific type when creating the index:
```sql
CREATE INDEX j1 ON mytest(j.group_id)
CREATE INDEX j2 ON mytest(UINT(j.year))
CREATE INDEX j3 ON mytest(FLOAT(j.latitude))
```

The first statement (the one with `j1` and without an explicit type cast) will
default to `UINT` and emit a warning. In the future, this warning might get
promoted to a hard error. Why?

The attribute index *must* know upfront what value type it indexes. At the same
time the engine can not assume any type for a JSON field, because hey, JSON!
Might not even *be* a single type across the entire field, might even change row
to row, which is perfectly legal. So the burden of casting your JSON fields to
a specific indexable type lies with you, the user.

Indexes on MVA (ie. sets of `UINT` or `BIGINT`) should also work:
```sql
CREATE INDEX tags ON mytest(tags)
```

Note that indexes over MVA can only currently improve performance on either
`WHERE ANY(mva) = ?` or `WHERE ANY(mva) IN (?, ?, ...)` types of queries.
For "rare enough" reference values we can read the final matching rows from the
index; that is usually quicker than scanning all rows; and for "too frequent"
values query optimizer will fall back to scanning. Everything as expected.

However, beware that in `ALL(mva)` case index will not be used yet! Because even
though technically we could read *candidate* rows (the very same ones as in
`ANY(mva)` cases), and scanning *just* the candidates could very well be still
quicker that a full scan, there are internal architecural issues that make such
an implementation much more complicated. Given that we also usually see just the
`ANY(mva)` queries in production, we postponed the `ALL(mva)` optimizations.
Those might come in a future release.

Here's an example where we create an index and speed up `ANY(mva)` query from
100 msec to under 1 msec, while `ALL(mva)` query still takes 57 msec.

```sql
mysql> select id, tags from t1 where any(tags)=1838227504 limit 1;
+------+--------------------+
| id   | tags               |
+------+--------------------+
|   15 | 1106984,1838227504 |
+------+--------------------+
1 row in set (0.10 sec)

mysql> create index tags on t1(tags);
Query OK, 0 rows affected (4.66 sec)

mysql> select id, tags from t1 where any(tags)=1838227504 limit 1;
+------+--------------------+
| id   | tags               |
+------+--------------------+
|   15 | 1106984,1838227504 |
+------+--------------------+
1 row in set (0.00 sec)

mysql> select id, tags from t1 where all(tags)=1838227504 limit 1;
Empty set (0.06 sec)
```

For the record, `t1` test collection had 5 million rows and 10 million `tags`
values, meaning that `CREATE INDEX` which completed in 4.66 seconds was going at
~1.07M rows/sec (and ~2.14M values/sec) indexing rate in this example. In other
words: creating an index is usually fast.

Attribute indexes can be created on both RT and plain indexes, `CREATE INDEX`
works either way. You can also use [`create_index`](#create_index-directive)
config directive for plain indexes. (The directive is not yet supported for RT
as of v.3.7, but we plan that support.)

Geosearches with `GEODIST()` can also benefit quite a lot from attribute
indexes. They can automatically compute a bounding box (or boxes) around
a static reference point, and then process only a fraction of data using
index reads. Refer to [Geosearches section](#searching-geosearches) for
more details.

### Query optimizer, and index hints

Query optimizer is the mechanism that decides, on a per-query basis, whether to
use or to ignore specific indexes to compute the current query.

The optimizer can usually choose any combination of any applicable indexes. The
specific index combination gets chosen based on cost estimates. Curiously, that
choice is not exactly completely obvious even when we have just 2 indexes.

For instance, assume that we are doing a geosearch, something like this:

```sql
SELECT ... FROM test1
WHERE (lat BETWEEN 53.23 AND 53.42) AND (lon BETWEEN -6.45 AND -6.05)
```

Assume that we have indexes on both `lat` and `lon` columns, and can use them.
More, we can get an exact final result set out of that index pair, without any
extra checks needed. But should we? Instead of using both indexes it is actually
sometimes more efficient to use just one! Because with 2 indexes, we have to:

1. Perform `lat` range index read, get X `lat` candidate rowids
2. Perform `lon` range index read, get Y `lon` candidate rowids
3. Intersect X and Y rowids, get N matching rowids
4. Lookup N resulting rows
5. Process N resulting rows

While when using 1 index on `lat` we only have to:

1. Perform `lat` range index read, get X `lat` candidate rowids
2. Lookup X candidate rows
3. Perform X checks for `lon` range, get N matching rows
4. Process N resulting rows

Now, `lat` and `lon` frequently are somewhat correlated. Meaning that X, Y, and
N values can all be pretty close. For example, let's assume we have 11K matches
in that specific latitude range, 12K matches in longitude range, and 10K final
matches, ie. `X = 11000, Y = 12000, N = 10000`. Then using just 1 index means
that we can avoid reading 12K `lat` rowids and then intersecting 23K rowids,
introducing, however, 2K extra row lookups and 12K `lon` checks instead. Guess
what, row lookups and extra checks are actually cheaper operations, and we are
doing less of them. So with a few quick estimates, using only 1 index out of 2
applicable ones suddenly looks like a better bet. That can be indeed confirm on
real queries, too.

And that's exactly how the optimizer works. Basically, it checks multiple
possible index combinations, tries to estimate the associated query costs, and
then picks the best one it finds.

However, the number of possible combinations grows explosively with the
attribute index count. Consider a rather crazy (but possible) case with as many
as 20 applicable indexes. That means more than 1 million possible "on/off"
combinations. Even quick estimates for *all* of them would take too much time.
There are internal limits in the optimizer to prevent that. Which in turn means
that eventually some "ideal" index set might not get selected. (But, of course,
that is a rare situation. Normally there are just a few applicable indexes, say
from 1 to 10, so the optimizer can afford "brute forcing" up to 1024 possible
index combinations, and does so.)

Now, perhaps even worse, both the count and cost estimates are just that, ie.
only estimates. They might be slightly off, or way off. The actual query costs
might be somewhat different than estimated when we execute the query.

For those reasons, optimizer might occasionally pick a suboptimal query plan.
In that event, or perhaps just for testing purposes, you can tweak its behavior
with `SELECT` hints, and make it forcibly use or ignore specific attribute
indexes. For a reference on the exact syntax and behavior, refer to
["Index hints clause"](#index-hints-clause).

### CREATE and DROP index performance

DISCLAIMER: your mileage may vary *enormously* here, because there are many
contributing factors. Still, we decided to provide at least *some* performance
datapoints.

Core count is not a factor because index creation and removal are both
single-threaded in v.3.4 that we used for these benchmarks.

**Scenario 1**, index with ~38M rows, ~20 columns, taking ~13 GB total. Desktop
with 3.7 GHz CPU, 32 GB RAM, SATA3 SSD.

`CREATE INDEX` on an `UINT` column with a few (under 1000) distinct values took
around 4-5 sec; on a pretty unique `BIGINT` column with ~10M different values it
took 26-27 sec.

`DROP INDEX` took 0.1-0.3 sec.


Using annotations
------------------

Sphinx v.3.5 introduces support for a special **annotations field** that lets
you store multiple short "phrases" (aka annotations) into it, and then match and
rank them individually. There's also an option to store arbitrary per-annotation
payloads as JSON, and access those based on what individual entries did match.

Annotations are small fragments of text (up to 64 tokens) *within* a full-text
field that you can later match and rank separately and individually. (Or not.
Regular matching and ranking also still works.)

Think of a ruled paper page with individual sequentially numbered lines, each
line containing an individual short phrase. That "page" is our full-text field,
its "lines" are the annotations, and you can:

  1. run special queries to match the individual "lines" (annotations);
  2. store per-annotation scores (to JSON), and use the best score for ranking;
  3. fetch a list of matched annotations numbers;
  4. slice arbitrary JSON arrays using that list.

Specific applications include storing multiple short text entries (like user
search queries, or location names, or price lists, etc) while still having them
associated with a single document.

### Annotations overview

Let's kick off with a tiny working example. We will use just 2 rows, store
multiple locations names in each, and index those as annotations.


```bash
# somewhere in .conf file
index atest
{
    type = rt
    field = annot

    annot_field = annot
    annot_eot = EOT
    ...
}
```
```sql
# our test data
mysql> insert into atest (id, annot) values
       (123, 'new york EOT los angeles'),
       (456, 'port angeles EOT new orleans EOT los cabos');
Query OK, 2 rows affected (0.00 sec)
```

Matching the *individual* locations with a regular search would, as you can
guess, be quite a grueling job. Arduous. Debilitating. Excruciating. Sisyphean.
Our keywords are all mixed up! But annotations are evidently gonna rescue us.

```sql
mysql> select id from atest where match('eot');
0 rows in set (0.00 sec)

mysql> select id from atest where match('@annot los angeles"');
+------+
| id   |
+------+
|  123 |
+------+
1 row in set (0.00 sec)

mysql> select id from atest where match('@annot new angeles"');
0 rows in set (0.00 sec)
```

While that query looks regular you can see that it behaves differently, thanks
to `@annot` being a special **annotations field** in our example. Note that
**only one annotations field per index** is supported at this moment.

What's different exactly?

First, querying for `eot` did not match anything. Because we have `EOT` (case
sensitive) configured via `annot_eot` as our special separator token. Separators
are only used as boundaries when indexing, to kinda "split" the field into the
individual annotations. But **separators are *not* indexed themselves**.

Second, querying for `los angeles` only matches document 123, but not 456. And
that is actually the *core* annotations functionality right there, which is
matching "within" the individual entries, not the entire field. Formal wording,
**explicitly matching within the annotations field must only match on just the
individual annotations entries.**

Document `456` mentions both `angeles` and `los` alright, but in two different
entries, in two different individual annotations that we had set apart using the
`EOT` separator. Hence, no match.

Mind, that only happens when we **explicitly** search in the annotations field,
calling it by name. **Implicit matching in annotations field works as usual.**

```sql
mysql> select id from atest where match('los angeles"');
+------+
| id   |
+------+
|  123 |
|  456 |
+------+
2 rows in set (0.00 sec)
```

**Explicit multi-field searches also trigger the "annotations matching" mode.**
Those must match as usual in the regular fields, but only match individual
entries in the annotations field.

```sql
... where match('@(title,content,annot) hello world')
```

Another thing, **only BOW (bag-of-words) syntax without operators** is supported
in the explicit annotations query "blocks" at the moment. But that affects just
those *blocks*, just the parts that explicitly require special matching in the
special fields, not even the rest of the query. Full-text operators are still
good anywhere *else* in the query. That includes combining multiple annotations
blocks using boolean operators.

```sql
# ERROR, operators in @annot block
... where match('@annot hello | world');
... where match('@annot hello << world');

# okay, operators outside blocks are ok
... where match('(@annot black cat) | (@title white dog)')
... where match('(@annot black cat) | (@annot white dog)')
```

The two erroneous queries above will fail with an "only AND operators are
supported in annotations field searches" message.

**All BOW keywords must match in the explicit "annotations matching" mode.**
Rather naturally, if we're looking for a `black cat` in an individual *entry*,
matching on `black` in entry one and `cat` in entry two isn't what we want.

On a side note, analyzing the query tree to forbid the nested operators seems
trivial at the first glance, but it turned out surprisingly difficult to
implement (so many corner cases). So in the initial v.3.5 roll-out some of the
operators may still slip and get accepted, even within the annotations block.
Please do **not** rely on that. That is **not** supported.

**You can access the matched annotations numbers** via the `ANNOTS()` function
and **you can slice JSON arrays with those numbers** via its `ANNOTS(j.array)`
variant. So you can store arbitrary per-entry metadata into Sphinx, and fetch
a metadata slice with just the matched entries.

Case in point, assume that your documents are phone models, and your annotations
are phone specs like "8g/256g pink", and you need prices, current stocks, etc
for every individual spec. You can store those per-spec values as JSON arrays,
match for "8g 256g" on a per-spec basis, and fetch just the matched prices.

```sql
SELECT ANNOTS(j.prices), ANNOTS(j.stocks) FROM phone_models
WHERE MATCH('@spec 8g 256g') AND id=123
```

And, of course, as all the per-entry metadata here is stored in a regular JSON
attribute, you can easily update it on the fly.

Last but not least, **you can assign optional per-entry scores to annotations**.
Briefly, you store scores in a JSON array, tag it as a special "scores" one, and
the max score over matched entries becomes an `annot_max_score` ranking signal.

That's it for the overview, more details and examples below.

### Annotations index setup

The newly added per-index config directives are `annot_field`, `annot_eot`, and
`annot_scores`. The latter one is optional, needed for ranking (not matching),
we will discuss that a bit later. The first two are mandatory.

The `annot_field` directive takes a single field name. We currently support just
one annotations field per index at the moment, seems both easier and sufficient.

The `annot_eot` directive takes a raw separator token. The "EOT" is not a typo,
it just means "end of text" (just in case you're curious). The separator token
is intentionally case-sensitive, so be careful with that.

For the record, we also toyed with an idea using just newlines or other special
characters for the separators, but that quickly proved incovenient and fragile.

To summarize, the minimal extra config to add an annotations fields is just two
extra lines. Pick a field, pick a separator token, and you're all set.

```bash
index atest
{
    ...
    annot_field = annot
    annot_eot = EOT
}
```

Up to 64 tokens per annotation are indexed. Any remaining tokens are thrown
away.

Individual annotations are numbered sequentially in the field, starting from 0.
Multiple EOT tokens are allowed. They create empty annotations entries (that
will never ever match). So in this example our two non-empty annotations entries
get assigned numbers 0 and 3, as expected.

```sql
mysql> insert into atest (id, annot) values
    -> (123, 'hello cat EOT EOT EOT hello dog');
Query OK, 1 row affected (0.00 sec)

mysql> select id, annots() from atest where match('@annot hello');
+------+----------+
| id   | annots() |
+------+----------+
|  123 | 0,3      |
+------+----------+
1 row in set (0.00 sec)
```

### Annotations scores

You can (optionally) provide your own custom per-annotation scores, and use
those for ranking. For that, you just store an array of per-entry scores into
JSON, and mark that JSON array using the `annot_scores` directive. Sphinx will
then compute `annot_max_score`, the max score over all the matched annotations,
and return it in `FACTORS()` as a document-level ranking signal. That's it, but
of course there are a few more boring details to discuss.

The `annot_scores` directive currently takes any top-level JSON key name.
(We may add support for nested keys in the future.) Syntax goes as follows.

```bash
# in general
annot_scores = <json_attr>.<scores_array>

# for example
annot_scores = j.scores

# ERROR, illegal, not a top-level key
annot_scores = j.sorry.maybe.later
```

For performance reasons, all scores must be floats. So the JSON arrays must be
float vectors. When in doubt, either use the `DUMP()` function to check that, or
just always use the `float[...]` syntax to enforce that.

```sql
INSERT INTO atest (id, annot, j) VALUES
(123, 'hello EOT world', '{"scores": float[1.23, 4.56]}')
```

As the scores are just a regular JSON attribute, you can add, update, or remove
them on the fly. So you can make your scores dynamic.

You can also manage to "break" them, ie. store a scores array with a mismatching
length, or wrong (non-float) values, or not even an array, etc. That's fine too,
there are no special safeguards or checks against that. Your data, your choice.
Sphinx will simply ignore missing or unsupported scores arrays when computing
the `annot_max_score` and return a zero.

The score array of a mismatching length is *not* ignored though. The scores that
*can* be looked up in that array *will* be looked up. So having just 3 scores is
okay even if you have 5 annotations entries. And vice versa.

In addition, regular scores should be non-negative (greater or equal to zero),
so the negative values will also be effectively ignored. For example, a scores
array with all-negative values like `float[-1,-2,-3]` will always return a zero
in the `annot_max_score` signal.

Here's an example that should depict (or at least sketch!) one of the intended
usages. Let's store additional keywords (eg. extracted from query logs) as our
annotations. Let's store per-keyword CTRs (click through ratios) as our scores.
Then let's match through both regular text and annotations, and pick the best
CTR for ranking purposes.

```bash
index scored
{
    ...
    annot_field = annot
    annot_eot = EOT
    annot_scores = j.scores
}
```

```sql
INSERT INTO scored (id, title, annot, j) VALUES
  (123, 'samsung galaxy s22',
    'flagship EOT phone', '{"scores": [7.4f, 2.7f]}'),
  (456, 'samsung galaxy s21',
    'phone EOT flagship EOT 2021', '{"scores": [3.9f, 2.9f, 5.3f]}'),
  (789, 'samsung galaxy a03',
    'cheap EOT phone', '{"scores": [5.3f, 2.1f]}')
```

Meaning that according to our logs these Samsung models get (somehow) found when
searching for either "flagship" or "cheap" or "phone", with the respective CTRs.
Now, consider the following query.

```sql
SELECT id, title, FACTORS() FROM scored
WHERE MATCH('flagship samsung phone')
OPTION ranker=expr('1')
```

We match the 2 flagship models (S21 and S22) on the extra annotations keywords,
but that's not important. A regular field would've worked just as well.

But! Annotations scores yield an extra ranking signal here. `annot_max_score`
picks the best score over the actually matched entries. We get 7.4 for document
123 from the `flagship` entry, and 3.9 for document 456 from the `phone` entry.
That's the max score over all the matched annotations, as promised. Even though
the *annotations* matching only happened on 1 keyword out of 3 keywords total.

```sql
*************************** 1. row ***************************
           id: 123
        title: samsung galaxy s22
pp(factors()): { ...
  "annot_max_score": 7.4, ...
}
*************************** 2. row ***************************
           id: 456
        title: samsung galaxy s21
pp(factors()): { ...
  "annot_max_score": 3.9, ...
}
```

And that's obviously a useful signal. In fact, in this example it could even
make *all* the difference between S21 and S22. Otherwise those documents would
be pretty much indistinguishable with regards to the "flagship phone" query.

However, beware of annotations syntax, and how it affects the regular matching!
Suddenly, the following query matches... absolutely nothing.

```sql
SELECT id, title, FACTORS() FROM scored
WHERE MATCH('@(title,annot) flagship samsung phone')
OPTION ranker=expr('1')
```

How come? Our matches just above happened in exactly the `title` and `annot`
fields anyway, the only thing we added was a simple field limit, surely the
matches must stay the same, and this must be a bug?

Nope. Not a bug. Because that `@annot` part is *not* a mere field limit anymore
with annotations on. Once we *explicitly* mention the annotations field, we also
engage the special "match me the entry" mode. Remember, all BOW keywords must
match in the explicit "annotations matching" mode. And as we do *not* have any
documents with *all* the 3 keywords in any of the annotations entries, oops,
zero matches.

### Accessing matched annotations

You can access the per-document lists of matched annotations via the `ANNOTS()`
function. There are currently two ways to use it.

  1. `ANNOTS()` called without arguments returns a comma-separated list of
     the matched annotations entries indexes. The indexes are 0-based.
  2. `ANNOTS(<json_array>)` called with a single JSON key argument returns the
     array slice with just the matched elements.

So you can store arbitrary per-annotation payloads either externally and grab
just the payload indexes from Sphinx using the `ANNOTS()` syntax, or keep them
internally in Sphinx as a JSON attribute and fetch them directly using the JSON
slicing syntax. Here's an example.

```sql
mysql> INSERT INTO atest (id, annot, j) VALUES
    -> (123, 'apples EOT oranges EOT pears',
    -> '{"payload":["red", "orange", "yellow"]}');
Query OK, 1 row affected (0.00 sec)

mysql> SELECT ANNOTS() FROM atest WHERE MATCH('apples pears');
+----------+
| annots() |
+----------+
| 0,2      |
+----------+
1 row in set (0.00 sec)

mysql> SELECT ANNOTS(j.payload) FROM atest WHERE MATCH('apples pears');
+-------------------+
| annots(j.payload) |
+-------------------+
| ["red","yellow"]  |
+-------------------+
1 row in set (0.00 sec)
```

Indexes missing from the array are simply omitted when slicing. If all indexes
are missing, NULL is returned. If the argument is not an existing JSON key, or
not an array, NULL is also returned.

```sql
mysql> SELECT id, j, ANNOTS(j.payload) FROM atest WHERE MATCH('apples pears');
+------+---------------------------------------+-------------------+
| id   | j                                     | annots(j.payload) |
+------+---------------------------------------+-------------------+
|  123 | {"payload":["red","orange","yellow"]} | ["red","yellow"]  |
|  124 | {"payload":["red","orange"]}          | ["red"]           |
|  125 | {"payload":{"foo":123}}               | NULL              |
+------+---------------------------------------+-------------------+
3 rows in set (0.00 sec)
```

As a side note (and for another example) using `ANNOTS()` on the scores array
discussed in the previous section will return the matched scores, as expected.

```sql
mysql> SELECT id, ANNOTS(j.scores) FROM scored
    -> WHERE MATCH('flagship samsung phone');
+------+------------------+
| id   | annots(j.scores) |
+------+------------------+
|  123 | [7.4,2.7]        |
|  456 | [3.9,2.9]        |
+------+------------------+
2 rows in set (0.00 sec)
```

However, the `annot_max_score` signal is still required. Because the internal
expression type returned from `ANNOTS(<json>)` is a string, not a "real" JSON
object. Sphinx can't compute the proper max value from that just yet.

### Annotation-specific ranking factors

Annotations introduce several new ranking signals. At the moment they all are
document-level, as we support just one annotations field per index anyway. The
names are:

  - `annot_exact_hit`
  - `annot_exact_order`
  - `annot_hit_count`
  - `annot_max_score`
  - `annot_sum_idf`

`annot_exact_hit` is a boolean flag that returns 1 when there was an exact hit
in any of the matched annotations entries, ie. if there was an entry completely
"equal" to what we searched for (in the annotations field). It's identical to
the regular `exact_hit` signal but works on individual annotations entries
rather than entire full-text fields.

`annot_exact_order` is a boolean flag that returns 1 when all the queried words
were matched in the exact order in any of the annotations entries (perhaps with
some extra words in between the matched ones). Also identical to `exact_order`
over individual annotations rather than entire fields.

`annot_hit_count` is an integer that returns the number of different annotation
*entries* matched. Attention, this is the number of ***entries***, and not the
keyword hits (postings) matched in those entries!

For example, `annot_hit_count` will be 1 with `@annot one` query matched against
`one two one EOT two three two` field, because exactly one annotations *entry*
matches, even though two postings match. As a side note, the number of matched
*postings* (in the entire field) will still be 2 in this example, of course, and
that is available via the `hit_count` per-field signal.

`annot_max_score` is a float that returns the max annotations score over the
matched annotations. See ["Annotations scores"](#annotations-scores) section for
details.

`annot_sum_idf` is a float that returns the `sum(idf)` over all the unique
keywords (not their occurrences!) that were matched. This is just a convenience
copy of the `sum_idf` value for the annotations field.

All these signals should appear in the `FACTORS()` JSON output based on whether
you have an annotations field in your index or not.

Beware that (just as any other conditional signals) they are accessible in
formulas and UDFs at *all* times, even for indexes without an annotations field.
The following two signals may return special NULL values:

  1. `annot_hit_count` is -1 when there is no `annot_field` at all. 0 means that
     we do have the annotations field, but nothing was matched.
  2. `annot_max_score` is -1 when there is no `annot_scores` configured at all.
     0 means that we do have the scores generally, but the current value is 0.


Using k-batches
----------------

K-batches ("kill batches") let you bulk delete older versions of the documents
(rows) when bulk loading new data into Sphinx, for example, adding a new delta
index on top of an older main archive index.

K-batches in Sphinx v.3.x replace k-lists ("kill lists") from v.2.x and before.
The major differences are that:

  1. They are *not* anonymous anymore.
  2. They are now only applied once on loading. (As oppposed to every search,
     yuck).

"Not anonymous" means that when loading a new index with an associated k-batch
into `searchd`, **you now have to explicitly specify target indexes** that it
should delete the rows from. In other words, "deltas" now *must* explicitly
specify all the "main" indexes that they want to erase old documents from,
at index-time.

The effect of applying a k-batch is equivalent to running (just once) a bunch
of `DELETE FROM X WHERE id=Y` queries, for every index X listed in `kbatch`
directive, and every document id Y stored in the k-batch. With the index format
updates this is now both possible, **even in "plain" indexes**, and quite
efficient too.

K-batch only gets applied once. After a successful application to all the target
indexes, the batch gets cleared.

So, for example, when you load an index called `delta` with the following
settings:

```bash
index delta
{
    ...
    sql_query_kbatch = SELECT 12 UNION SELECT 13 UNION SELECT 14
    kbatch = main1, main2
}
```

The following (normally) happens:

  * `delta` kbatch file is loaded
    * in this example it will have 3 document ids: 12, 13, and 14
  * documents with those ids are deleted from `main1`
  * documents with those ids are deleted from `main2`
  * `main1`, `main2` save those deletions to disk
  * if all went well, `delta` kbatch file is cleared

All these operations are pretty fast, because deletions are now internally
implemented using a bitmap. So deleting a given document by id results in a hash
lookup and a bit flip. In plain speak, very quick.

"Loading" can happen either by restarting or rotation or whatever, k-batches
should still try to apply themselves.

Last but not least, you can also use `kbatch_source` to avoid explicitly
storing all newly added document ids into a k-batch, instead, you can use
`kbatch_source = kl, id` or just `kbatch_source = id`; this will automatically
add all the document ids from the index to its k-batch. The default value is
`kbatch_source = kl`, that is, to use explicitly provided docids only.


Doing bulk data loads
----------------------

TODO: describe rotations (legacy), RELOAD, ATTACH, etc.


Using JSON
-----------

For the most part using JSON in Sphinx should be very simple. You just put
pretty much arbitrary JSON in a proper column (aka attribute). Then you just
access the necessary keys using a `col1.key1.subkey2.subkey3` syntax. Or, you
access the array values using `col1.key1[123]` syntax. And that's it.

For a literally 30-second kickoff, you can configure a test RT index like this:

```bash
index jsontest
{
    type = rt
    path = data/jsontest
    field = title
    attr_json = j
}
```

Then restart `searchd` or reload the config, and fire away a few test queries:

```sql
mysql> INSERT INTO jsontest (id, j) VALUES (1, '{"foo":"bar", "year":2019,
  "arr":[1,2,3,"yarr"], "address":{"city":"Moscow", "country":"Russia"}}');
Query OK, 1 row affected (0.00 sec)

mysql> SELECT j.foo FROM jsontest;
+-------+
| j.foo |
+-------+
| bar   |
+-------+
1 row in set (0.00 sec)

mysql> SELECT j.year+10, j.arr[3], j.address.city FROM jsontest;
+-----------+----------+----------------+
| j.year+10 | j.arr[3] | j.address.city |
+-----------+----------+----------------+
|    2029.0 | yarr     | Moscow         |
+-----------+----------+----------------+
1 row in set (0.00 sec)
```

However, sometimes that is not quite enough (mostly for performance reasons),
and thus we have both several Sphinx-specific **JSON syntax extensions**, and
several **important internal implementation details** to discuss, including
a few Sphinx-specific limits. Briefly, those are as follows:

  * optimized scalar storage (for `int8`, `int32`, `int64`, `bool`, `float`, and
    `NULL` types)
  * optimized array storage (for `int8`, `int32`, `int64`, `float`, `double`,
    and `string` types)

  * `0.0f` (and `0.0f32`) syntax extension for 32-bit float values
  * `0.0d` (and `0.0f64`) syntax extension for 64-bit double values
  * `int8[]`, `float[]`, and `double[]` syntax extensions for 8-bit integer,
    32-bit float and 64-bit double arrays, respectively

**Optimized storage** means that *usually* Sphinx auto-detects the actual value
types, both for standalone values and for arrays, and then uses the smallest
storage type that works.

So when a 32-bit (4-byte) integer is enough for a numeric value, Sphinx would
automatically store just that. If that overflows, no need to worry, Sphinx would
just automatically switch to 8-byte integer values, or even `double` values
(still 8-byte).

Ditto for arrays. When your arrays contain a mix of actual types, Sphinx handles
that just fine, and stores a generic array, where every element has a different
type attached to it. However, when your array only actually contains one very
specific type (for example, regular 32-bit integers only), Sphinx auto-detects
that fact, and stores *that* array in an optimized manner, using just 4 bytes
per value, and skipping the repeated types. All the built-in functions support
all such optimized array types, and have a special fast codepath to handle them,
in a transparent fashion.

As of v.3.2, array value types that might get optimized that way are `int8`,
`int32`, `int64`, `float`, `double`, and `string`. This covers pretty much all
the usual numeric types, and therefore all you have to do to ensure that the
optimizations kick in is, well, to only use one actual type in your data.

So everything is on autopilot, mostly. However, there are several exceptions to
that rule that still require a tiny bit of effort from you!

**First, there might be a catch with `float` vs `double` types.** Sphinx now
uses 32-bit `float` by default, starting from v.3.7. But JSON standard (kinda)
pushes for high-precision, 64-bit `double` type. So longer bigger values won't
round-trip by default.

We consider that a non-issue. We find that for **all** our applications `float`
is quite enough, saves both storage and CPU, and it's okay to default to float.
However, you can still force Sphinx to default to `double` storage if really
needed. Just set `json_float = double` in your config.

Or, you can explicitly specify types on a per-value basis. Sphinx has a syntax
extension for that.

The regular `{"scale": 1.23}` JSON syntax now stores either a 4-byte float or
an 8-byte double, depending on the `json_float` setting. But with an explicit
type suffix the setting does not even apply. So `{"scale": 1.23f}` always stores
a 4-byte float, and `{"scale": 1.23d}` an 8-byte double.

You can also use bigger, longer, and more explicit `f32` and `f64` suffixes,
as in `{"scale": 1.23f32}` and `{"scale": 1.23f64}`.

**Second, `int8` arrays must be explicit.** Even though Sphinx can auto-detect
the fact that all your array values are integers in the -128 to 127 range, and
can be stored efficiently using just 1 byte per value, it does *not* just make
that assumption, and uses `int32` type instead.

And this happens because there is no way for Sphinx to tell by looking at *just*
those values whether you really wanted an optimized `int8` vector, or the intent
was to just have a placeholder (filled with either `0`, or `-1`, or what have
you) `int32` vector for future updates. Given that JSON updates are currently
in-place, at this decision point Sphinx chooses to go with the more conservative
but flexible route, and store an `int32` vector even for something that could be
store more efficiently like `[0, 0, 0, 0]`.

To force that vector into super-slim 1-byte values, you *have* to use a syntax
extension, and use `int8[0, 0, 0, 0]` as your value.

**Third, watch out for integer vs float mixes.** The auto-detection happens
on a per-value basis. Meaning that an array value like `[1, 2, 3.0]` will be
marked as mixing two different types, `int32` and either `float` or `double`
(depending on the `json_float` setting). So neither the `int32` nor (worse)
`double` array storage optimization can kick in for this particular array.

You can enforce any JSON-standard type on Sphinx here using regular JSON syntax.
To store it as integers, you should simply get rid of that pesky dot that
triggers doubles, and use `[1, 2, 3]` syntax. For floats, on the contrary,
the dot should be everywhere, ie. you should use `[1.0, 2.0, 3.0]` syntax.

Finally, for the non-standard `float` type extension, you can also use the `f`
suffix, ie. `[1.0f, 2.0f, 3.0f]` syntax. But that might be inconvenient, so you
can also use the `float[1, 2, 3.0]` syntax instead. Either of these two forms
enables Sphinx to auto-convert your vector to nice and fast optimized floats.

For the record, that also works for doubles, `[1.0d, 2.0d, 3.0d]` and
`double[1,2,3]` forms are both legal syntax too.

That was all about the values though. What about the keys?

**Keys are stored as is.** Meaning that if you have a `superLongKey` in
(almost) every single document, that key will be stored as a plain old text
string, and repeated as many times as there are documents. And all those
repetitions would consume some RAM bytes. Flexible, but not really efficient.

So the rule of thumb is, super-long key names are, well, okay, but not really
great. Just as with regular JSON. Of course, for smaller indexes the savings
might just be negligible. But for bigger ones, you might want to consider
shorter key names.

### JSON comparison quirks

Comparisons with JSON can be a little tricky when it comes to value types.
Especially the numeric ones, because of all the `UINT` vs `FLOAT` vs `DOUBLE`
jazz. (And, mind you, by default the floating-point values might be stored
either as `FLOAT` or `DOUBLE`.) Briefly, beware that:

1. String comparisons are strict, and require the string type.

   Meaning that `WHERE j.str1='abc'` check must only pass when *all* the
   following conditions are true: 1) `str1` key exists; 2) `str1` value type is
   exactly `string`; 3) the value matches.

   Therefore, for a sudden *integer* value compared against a string constant,
   for example, `{"str1":123}` value against a `WHERE j.str1='123'` condition,
   the check will fail. As it should, there are no implicit conversions here.

2. Numeric comparisons against integers match any numeric type, not just
   integers.

   Meaning that both `{"key1":123}` and `{"key1":123.0}` values must pass the
   `WHERE j.key1=123` check. Again, as expected.

3. Numeric comparisons against floats *forcibly* convert double values to
   (single-precision) floats, and roundoff issues may arise.

   Meaning that when you store something like `{"key1":123.0000001d}` into your
   index, then the `WHERE j.key1=123.0` check will pass, because roundoff to
   `float` looses that fractional part. However, at the same time
   `WHERE j.key1=123` check will *not* pass, because *that* check will use the
   original double value and compare it against the integer constant.

   This might be a bit confusing, but otherwise (without roundoff) the
   situation would be arguably worse: in an even more counter-intuitive fashion,
   `{"key1":2.22d}` does *not* pass the `WHERE j.key1>=2.22` check, because the
   reference constant here is `float(2.22)`, and then because of rounding,
   `double(2.22) < float(2.22)`!

TODO: describe limits, json_xxx settings, our syntax extensions, etc.


Using array attributes
-----------------------

Array attributes let you save a fixed amount of integer or float values into
your index. The supported types are:

  * `attr_int_array` that stores signed 32-bit integers;
  * `attr_int8_array` that stores signed 8-bit integers (-128 to 127 range);
  * `attr_float_array` that stores 32-bit floats.

To declare an array attribute, use the following syntax in your index:

```bash
attr_{int|int8|float}_array = NAME[SIZE]
```

Where `NAME` is the attribute name, and `SIZE` is the array size, in elements.
For example:

```bash
index rt
{
    type = rt

    field = title
    field = content

    attr_uint = gid # regular attribute
    attr_float_array = vec1[5] # 5D array of floats
    attr_int8_array = vec2[7] # 7D array of small 8-bit integers
    # ...
}
```

The array dimensions must be between 2 and 8192, inclusive.

The array gets aligned to the nearest 4 bytes. This means that an `int8_array`
with 17 elements will actually use 20 bytes for storage.

The expected input array value for both `INSERT` queries and source indexing
must be either:

  * a comma or space-separated string with the values;
  * or an empty string;
  * or (special case for INT8 arrays only) a string with "base64:" prefix.

```sql
INSERT INTO rt (id, vec1) VALUES (123, '3.14, -1, 2.718, 2019, 100500');
INSERT INTO rt (id, vec1) VALUES (124, '');

INSERT INTO rt (id, vec2) VALUES (125, '77, -66, 55, -44, 33, -22, 11');
INSERT INTO rt (id, vec2) VALUES (126, 'base64:Tb431CHqCw=');
```

Empty strings will zero-fill the array. Non-empty strings are subject to strict
validation. First, there must be exactly as many values as the array can hold.
So you can not store 3 or 7 values into a 5-element array. Second, the values
ranges are also validated. So you will not be able to store a value of 1000
into an `int8_array` because it's out of the -128..127 range.

Base64-encoded data string must decode into exactly as many bytes as the array
size is, or that's an error. Trailing padding is not required, but overpadding
(that is, having over 2 trailing `=` chars) also is an error, an invalid array
value.

Base64 is only supported for INT8 arrays at the moment. That's where the biggest
savings are. FLOAT and other arrays are viable too, so once we start seeing
datasets that can benefit from encoding, we can support those too.

Attempting to `INSERT` an invalid array value will fail. For example:

```sql
mysql> INSERT INTO rt (id, vec1) VALUES (200, '1 2 3');
ERROR 1064 (42000): bad array value

mysql> INSERT INTO rt (id, vec1) VALUES (200, '1 2 3 4 5 6');
ERROR 1064 (42000): bad array value

mysql> INSERT INTO rt (id, vec2) VALUES (200, '0, 1, 2345');
ERROR 1064 (42000): bad array value

mysql> INSERT INTO rt (id, vec2) VALUES (200, 'base64:AQID');
ERROR 1064 (42000): bad array value
```

However, when batch indexing with `indexer`, an invalid array value will be
reported as a warning, and zero-fill the array, but it will **not** fail the
entire indexing batch.

Back to the special base64 syntax, it helps you save traffic and/or *source*
data storage for the longer INT8 arrays. We can observe those savings even in
the simple example above, where the longer `77 -66 55 -44 33 -22 11` input and
the shorter `base64:Tb431CHqCw=` one encode absolutely *identical* arrays.

The difference gets even more pronounced on longer arrays. Consider for example
this 24D one with a bit of real data (and mind that 24D is still quite small,
actual embeddings would be significantly bigger).

```sql
/* text form */
'-58 -71 21 -56 -5 40 -8 6 69 14 11 0 -41 -64 -12 56 -8 -48 -35 -21 23 -2 9 -66'

/* base64 with prefix, as it should be passed to Sphinx */
'base64:xrkVyPso+AZFDgsA18D0OPjQ3esX/gm+'

/* base64 only, eg. as stored externally */
'xrkVyPso+AZFDgsA18D0OPjQ3esX/gm+'
```

Both versions take exactly 24 bytes in Sphinx, but the base64 encoded version
can save a bunch of space in your *other* storages that you might use (think CSV
files, or SQL databases, etc).

`UPDATE` queries should now also support the special base64 syntax. `BULK` and
`INPLACE` update types are good too. INT8 array updates are naturally inplace.

```sql
UPDATE rt SET vec2 = 'base64:Tb431CHqCw=' WHERE id = 2;
BULK UPDATE rt (id, vec2) VALUES (2, 'base64:Tb431CHqCw=');
```

Last but not least, how to use the arrays from here?

Of course, there's always storage, ie. you could just fetch arrays from Sphinx
and pass them elsewhere. But native support for these arrays in Sphinx means
that some native processing can happen within Sphinx too.

At the moment, pretty much the only "interesting" built-in functions that work
on array arguments are `DOT()` and `L1DIST()`, so you can compute a dot product
(or a Manhattan distance) between an array and a constant vector. Did we mention
embeddings and vector searches? Yeah, that.

```sql
mysql> SELECT id, DOT(vec1,FVEC(1,2,3,4,5)) d FROM rt;
+------+--------------+
| id   | d            |
+------+--------------+
|  123 | 510585.28125 |
|  124 |            0 |
+------+--------------+
2 rows in set (0.00 sec)
```


Using blob attributes
----------------------

We added `BLOB` type support in v.3.5 to store variable length binary data.
You can declare blobs using the respective `attr_blob` directive in your index.
For example, the following creates a RT index with 1 string and 1 blob column.

```bash
index rt
{
    type        = rt
    path        = ./data/rt
    field       = title
    attr_string = str1
    attr_blob   = blob2
}
```

The major difference from `STRING` type is the **embedded zeroes handling**.
Strings auto-convert them to spaces when storing the string data, because
strings are zero-terminated in Sphinx. (And, for the record, when searching,
strings are currently truncated at the first zero.) Blobs, on the other hand,
must store all the embedded zeroes verbatim.

```sql
mysql> insert into rt (id, str1, blob2) values (123, 'foo\0bar', 'foo\0bar');
Query OK, 1 row affected (0.00 sec)

mysql> select * from rt where str1='foo bar';
+------+---------+------------------+
| id   | str1    | blob2            |
+------+---------+------------------+
|  123 | foo bar | 0x666F6F00626172 |
+------+---------+------------------+
1 row in set (0.00 sec)
```

Note how the `SELECT` with a space matches the row. Because the zero within
`str1` was auto-converted during the `INSERT` query. And in the `blob2` column
we can still see the original zero byte.

For now, you can only store and retrieve blobs. Additional blob support (as in,
in `WHERE` clauses, expressions, escaping and formatting helpers) will be added
later as needed.

The default hex representation (eg. `0x666F6F00626172` above) is currently used
for client `SELECT` queries only, to avoid any potentional encoding issues.


Using mappings
---------------

Mappings are a text processing pipeline part that, basically, lets you map
keywords to keywords. They come in several different flavors. Namely, mappings
can differ:

 - by term count: either "simple" 1:1, or generic "multiword" M:N;
 - by text processing stage: either pre-morphology, or post-morphology;
 - by scope: either global, or document-only.

We still differentiate between **1:1 mappings** and **M:N mappings**, because
there is one edge case where we have to, see below.

**Pre-morphology** and **post-morphology** mappings, or pre-morph and post-morph
for short, are applied before and after morphology respectively.

**Document-only** mappings only affect documents while indexing, and never
affect the queries. As opposed to **global** ones, which affect both documents
*and* queries.

Most combinations of all these flavors work together just fine, but with one
exception. **At post-morphology stage, only 1:1 mappings are supported**; mostly
for operational reasons. While simply enabling post-morph M:N mappings at the
engine level is trivial, carefully handling the edge cases in the engine and
managing the mappings afterwards seems hard. Because *partial* clashes between
multiword pre-morph and post-morph mappings are too fragile to configure, too
complex to investigate, and most importantly, not even really required for
production. All other combinations are supported:

| Terms | Stage      | Scope    | Support | New        |
|-------|------------|----------|---------|------------|
| 1:1   | pre-morph  | global   | yes     | yes        |
| M:N   | pre-morph  | global   | yes     | -          |
| 1:1   | pre-morph  | doc-only | yes     | yes        |
| M:N   | pre-morph  | doc-only | yes     | -          |
| 1:1   | post-morph | global   | yes     | -          |
| M:N   | post-morph | global   | -       | -          |
| 1:1   | post-morph | doc-only | yes     | -          |
| M:N   | post-morph | doc-only | -       | -          |

"New" column means that this particular type is supported now, but was *not*
supported by the legacy `wordforms` directive. Yep, that's correct! Curiously,
simple 1:1 pre-morph mappings were indeed *not* easily available before.

Mappings reside in a separate text file (or a set of files), and can be used in
the index with a `mappings` directive.

You can specify either just one file, or several files, or even OS patterns like
`*.txt` (the latter should be expanded according to your OS syntax).

```bash
index test1
{
    mappings = common.txt test1specific.txt map*.txt
}
```

Semi-formal file syntax is as follows. (If it's too hard, worry not, there will
be an example just a little below.)

```bash
mappings := line, [line, [...]]
line := {comment | mapping}
comment := "#", arbitrary_text

mapping := input, separator, output, [comment]
input := [flags], keyword, [keyword, [...]]
separator := {"=>" | ">"}
output := keyword, [keyword, [...]]
flags := ["!"], ["~"]
```

So generally mappings are just two lists of keywords (input list to match, and
output list to replace the input with, respectively) with a special
`=>` separator token between them. Legacy `>` separator token is also still
supported.

Mappings not marked with any flags are pre-morphology.

Post-morphology mappings are marked with `~` flag in the very beginning.

Document-only mappings are marked with `!` flag in the very beginning.

The two flags can be combined.

Comments begin with `#`, and everything from `#` to the end of the current line
is considered a comment, and mostly ignored.

Magic `OVERRIDE` substring anywhere in the comment suppresses mapping override
warnings.

Now to the example! Mappings are useful for a variety of tasks, for instance:
correcting typos; implementing synonyms; injecting additional keywords into
documents (for better recall); contracting certain well-known phrases (for
performance); etc. Here's an example that shows all that.

```bash
# put this in a file, eg. mymappings.txt
# then point Sphinx to it
#
# mappings = mymappings.txt

# fixing individual typos, pre-morph
mapings => mappings

# fixing a class of typos, post-morph
~sucess => success

# synonyms, also post-morph
~commence => begin
~gobbledygook => gibberish
~lorry => truck # random comment example

# global expansions
e8400 => intel e8400

# global contractions
core 2 duo => c2d

# document-only expansions
# (note that semicolons are for humans, engine will ignore them)
!united kingdom => uk; united kingdom; england; scotland; wales
!grrm => grrm george martin

# override example
# this is useful when using multiple mapping files
# (eg. with different per-category mapping rules)
e8400 => intel cpu e8400 # OVERRIDE
```

### Pre-morph mappings

**Pre-morph mappings** are more "precise" in a certain sense, because they only
match specific forms, before any morphological normalization. For instance,
`apple trees => garden` mapping will *not* kick in for a document mentioning
just a singular `apple tree`.

Pre-morph mapping outputs are processed further as per index settings, and so
they are **subject to morphology** when the index has that enabled! For example,
`semiramis => hanging gardens` mapping with `stem_en` stemmer should result in
`hang garden` text being stored into index.

To be completely precise, in this example the *mapping* emits `hanging` and
`gardens` tokens, and then the subsequent *stemmer* normalizes them to `hang`
and `garden` respectively, and then (in the absence of any other mappings etc),
those two tokens are stored in the final index.

### Post-morph mappings

There is one very important caveat about the post-morph mappings.

**Post-morph mapping outputs are not morphology normalized** automatically,
only their **inputs** are. In other words, only the left (input) part is subject
to morphology, the output is stored into the index as is. More or less naturally
too, they are **post** morphology mappings, after all. Sill, that can very well
cause subtle-ish configuration bugs.

For example, `~semiramis => hanging gardens` mapping with `stem_en` will store
`hanging gardens` into the index, not `hang garden`, because no morphology for
outputs.

This is obviously *not* our intent, right?! We actually want `garden hang` query
to match documents mentioning either `semiramis` or `hanging gardens`, but with
*this* configuration, it will only match the former. So for now, we have to
**manually** morph our outputs (no syntax to automatically morph them just yet).
That would be done with a `CALL KEYWORDS` statement:

```sql
mysql> CALL KEYWORDS('hanging gardens', 'stem_test');
+------+-----------+------------+
| qpos | tokenized | normalized |
+------+-----------+------------+
| 1    | hanging   | hang       |
| 2    | gardens   | garden     |
+------+-----------+------------+
2 rows in set (0.00 sec)
```

So our mapping should be changed to `~semiramis => hang garden` in order to work
as expected. Caveat!

As a side note, both the original and updated mappings also affect any documents
mentioning `semirami` or `semiramied` (because morphology for inputs), but that
is rarely an issue.

Bottom line, keep in mind that **"post-morph mappings = morphed inputs, but
UNMOPRHED outputs"**, configure your mappings accordingly, and do *not* forget
to morph the outputs if needed!

In simple cases (eg. when you only use lemmatization) you might eventually get
away with "human" (natural language) normalization. One might reasonably guess
that the lemma for `gardens` is going to be just `garden`, right?! Right.

However, even our simple example is not that simple, because of innocuously
looking `hanging`, because look how `lemmatize_en` *actually* normalizes those
different forms of `hang`:

```sql
mysql> CALL KEYWORDS('hang hanged hanging', 'lemmatize_test');
+------+-----------+------------+
| qpos | tokenized | normalized |
+------+-----------+------------+
| 1    | hang      | hang       |
| 2    | hanged    | hang       |
| 3    | hanging   | hanging    |
+------+-----------+------------+
3 rows in set (0.00 sec)
```

It gets worse with more complex morphology stacks (where multiple `morphdict`
files, stemmers, or lemmatizers can engage). In fact, it gets worse with just
stemmers. For example, another classic caveat, `stem_en` normalizes `business`
to `busi` and one would need to use *that* in the output. Less easy to guess...
Hence the current rule of thumb, run your outputs through `CALL KEYWORDS` when
configuring, and use the normalized tokens.

Full disclosure, we consider additional syntax to mark the outputs to auto-run
through morphology (that would be so much easier to use than having to manually
filter through `CALL KEYWORDS`, right?!) but that's not implemented just yet.

### Document-only mappings

**Document-only mappings** are only applied to documents at indexing time, and
ignored at query time. This is pretty useful for indexing time expansions, and
that is why the `grrm` mapping example above maps it to itself too, and not just
`george martin`.

In the "expansion" usecase, they are more efficient when *searching*, compared
to similar regular mappings.

Indeed, when searching for a source mapping, regular mappings would expand to
all keywords (in our example, to all 3 keywords, `grrm george martin`), fetch
and intersect them, and do all that work for... nothing! Because we can obtain
exactly the same result much more efficiently by simply fetching just the source
keywords (just `grrm` in our example). And that's exactly how document-only
mappings work when querying, they just skip the *query* expansion altogether.

Now, when searching for (a part of) a destination mapping, nothing would change.
In that case both document-only and regular global mappings would just execute
the query completely identically. So `george` must match in any event.

Bottom line, use document-only mappings when you're doing expansions, in order
to avoid that unnecessary performance hit.


Using morphdict
----------------

**Morphdict** essentially lets you provide your own (additional) morphology
dictionary, ie. specify a list of form-to-lemma normalizations. You can think of
them as of "overrides" or "patches" that take priority over any other morphology
processors. Naturally, they also are 1:1 only, ie. they **must** map a single
morphological form to a single lemma or stem.

There may be multiple `morphdict` directives specifying multiple morphdict
files (for instance, with patches for different languages).

```bash
index test1
{
    morphdict = mymorph_english.txt
    morphdict = mymorph_spanish.txt
    ...
}
```

For example, we can use `morphdict` to fixup a few well-known mistakes that the
`stem_en` English stemmer is known to make.

```bash
octopii => octopus
business => business
businesses => business
```

Morphdict also lets you **specify POS (Part Of Speech) tags** for the lemmas,
using a small subset of Penn syntax. For example:

```bash
mumps => mumps, NN # always plural
impignorating => impignorate, VB
```

Simple 1:1 normalizations, optional POS tags, and comments are everything there
is to morphdict. Yep, it's as simple as that. Just for the sake of completeness,
semi-formal syntax is as follows.

```bash
morphdict := line, [line, [...]]
line := {comment | entry}
comment := "#", arbitrary_text

entry := keyword, separator, keyword, ["," postag], [comment]
separator := {"=>" | ">"}
postag := {"JJ" | "NN" | "RB" | "VB"}
```

Even though right now POS tags are only used to identify nouns in queries and
then compute a few related ranking signals, we decided to support a little more
tags than that.

 * `JJ`, adjective
 * `NN`, noun
 * `RB`, adverb
 * `VB`, verb

Optional POS tags are rather intended to fixup built-in lemmatizer mistakes.
However they should work alright with stemmers too.

When fixing up stemmers you generally have to proceed with extreme care, though.
Say, the following `stem_en` fixup example will *not* work as expected!

```bash
geese => goose
```

Problem is, `stem_en` stemmer (unlike `lemmatize_en` lemmatizer) does *not*
normalize `goose` to itself. So when `goose` occurs in the document text, it
will emit `goos` stem instead. So in order to fixup `stem_en` stemmer, you have
to map to that *stem*, with a `geese => goos` entry. Extreme care.


Migrating legacy wordforms
---------------------------

Mappings and morphdict were introduced in v.3.4 in order to replace the legacy
`wordforms` directive. Both the directive and older indexes are still supported
by v.3.4 specifically, of course, to allow for a smooth upgrade. However, they
are slated for quick removal.

How to migrate legacy wordforms properly? That depends.

To change the behavior minimally, you should extract 1:1 legacy wordforms into
`morphdict`, because legacy 1:1 wordforms replace the morphology. All the other
entries can be used as `mappings` rather safely. By the way, our loading code
for legacy `wordforms` works exactly this way.

However, unless you are using legacy wordforms to emulate (or implement even)
morphology, chances are quite high that your 1:1 legacy wordforms were intended
more for `mappings` rather than `morphdict`. In which event you should simply
rename `wordforms` directive to `mappings` and that would be it.


Using UDFs
-----------

### UDFs overview

Sphinx supports User Defined Functions (or UDFs for short) that let you extend
its expression engine:

```sql
SELECT id, attr1, myudf(attr2, attr3+attr4) ...
```

You can load and unload UDFs into `searchd` dynamically, ie. without having to
restart the daemon itself, and then use them in most expressions when searching
and ranking. Quick summary of the UDF features is as follows.

   * UDFs can accept most of the argument types that Sphinx supports, namely:
     - **numerics**, ie. integers (32-bit and 64-bit) and floats (32-bit);
     - **MVAs**, ie. sets of integers (32-bit and 64-bit);
     - **strings**, including binary non-ASCIIZ blobs;
     - **`FACTORS()`**, ie. special blobs with ranking signals;
     - **JSON objects**, including subobjects or individual fields;
     - **float vectors**.
   * UDFs can return integer, float, or string values.
   * UDFs can check the argument number, types, and names during the query
     setup phase, and raise errors.

UDFs have a wide variety of uses, for instance:

  * adding custom mathematical or string functions;
  * accessing the database or files from within Sphinx;
  * implementing complex ranking functions.

UDFs reside in the external dynamic libraries (`.so` files on UNIX and `.dll` on
Windows systems). Library files need to reside in a trusted folder specified by
`plugin_dir` directive (or in `$datadir/plugins` folder in datadir mode), for
obvious security reasons: securing a single folder is easy; letting anyone
install arbitrary code into `searchd` is a risk.

You can load and unload them dynamically into `searchd` with `CREATE FUNCTION`
and `DROP FUNCTION` SphinxQL statements, respectively. Also, you can seamlessly
reload UDFs (and other plugins) with `RELOAD PLUGINS` statement. Sphinx keeps
track of the currently loaded functions, that is, every time you create or drop
an UDF, `searchd` writes its state to the `sphinxql_state` file as a plain good
old SQL script.

Once you successfully load an UDF, you can use it in your `SELECT` or other
statements just as any of the built-in functions:

```sql
SELECT id, MYCUSTOMFUNC(groupid, authorname), ... FROM myindex
```

Multiple UDFs (and other plugins) may reside in a single library. That library
will only be loaded once. It gets automatically unloaded once all the UDFs and
plugins from it are dropped.

Aggregation functions are not supported just yet. In other words, your UDFs will
be called for just a single document at a time and are expected to return some
value for that document. Writing a function that can compute an aggregate value
like `AVG()` over the entire group of documents that share the same `GROUP BY`
key is not yet possible. However, you can use UDFs within the built-in aggregate
functions: that is, even though `MYCUSTOMAVG()` is not supported yet,
`AVG(MYCUSTOMFUNC())` should work alright!

UDFs are local. In order to use them on a cluster, you have to put the same
library on all its nodes and run proper `CREATE FUNCTION` statements on all the
nodes too. This might change in the future versions.

### UDF programming introduction

The UDF interface is plain C. So you would usually write your UDF in C or C++.
(Even though in theory it might be possible to use other languages.)

Your very first starting point should be `src/udfexample.c`, our example UDF
library. That library implements several different functions, to demonstrate how
to use several different techniques (stateless and stateful UDFs, different
argument types, batched calls, etc).

The files that provide the UDF interface are:

  * `src/sphinxudf.h` that declares the essential types and helper functions;
  * `src/sphinxudf.c` that implements those functions.

For UDFs that **do not** implement ranking, and therefore do not need to handle
`FACTORS()` arguments, simply including the `sphinxudf.h` header is sufficient.

To be able to parse the `FACTORS()` blobs from your UDF, however, you will also
need to compile and link with `sphinxudf.c` source file.

Both `sphinxudf.h` and `sphinxudf.c` are standalone. So you can copy around
those files only. They do not depend on any other bits of Sphinx source code.

Within your UDF, you should literally implement and export just two functions.

**First**, you must define `int <LIBRARYNAME>_ver() { return SPH_UDF_VERSION; }`
in order to implement UDF interface version control. `<LIBRARYNAME>` should be
replaced with the name of your library. Here's an example:

```c
#include <sphinxudf.h>

// our library will be called udfexample.so, thus, it must define
// a version function named udfexample_ver()
int udfexample_ver()
{
    return SPH_UDF_VERSION;
}
```

This version checker protects you from accidentally loading libraries with
mismatching UDF interface versions. (Which would in turn usually cause either
incorrect behavior or crashes.)

**Second**, you must implement the actual function, too. For example:

```c
sphinx_int64_t testfunc(SPH_UDF_INIT * init, SPH_UDF_ARGS * args,
    char * error_message)
{
   return 123;
}
```

UDF function names in SphinxQL are case insensitive. However, the respective
C/C++ **function names must be all lower-case**, or the UDF will fail to load.

More importantly, it is vital that:

  1. the calling convention is C (aka `__cdecl`);
  2. arguments list matches the plugin system expectations exactly;
  3. the return type matches the one you specify in `CREATE FUNCTION`;
  4. the implemented C/C++ functions are thread-safe.

Unfortunately, there is no (easy) way for `searchd` to automatically check for
those mistakes when loading the function, and they could crash the server and/or
result in unexpected results.

Let's discuss the simple `testfunc()` example in a bit more detail.

The first argument, a pointer to `SPH_UDF_INIT` structure, is essentially just
a pointer to our function state. Using that state is optional. In this example,
the function is stateless, it simply returns 123 every time it gets called.
So we do not have to define an initialization function, and we can simply
ignore that argument.

The second argument, a pointer to `SPH_UDF_ARGS`, is the most important one.
All the actual call arguments are passed to your UDF via this structure. It
contains the call argument count, names, types, etc. So whether your function
gets called like with simple constants, like this:

```sql
SELECT id, testfunc(1) ...
```

or with a bunch of subexpressions as its arguments, like this:

```sql
SELECT id, testfunc('abc', 1000*id+gid, WEIGHT()) ...
```

or anyhow else, it will receive the very same `SPH_UDF_ARGS` structure, in
**all** of these cases. However, the *data* passed in the args structure can be
a little different.

In the `testfunc(1)` call example `args->arg_count` will be set to 1, because,
naturally we have just one argument. In the second example, `arg_count` will be
equal to 3. Also `args->arg_types` array will contain different type data for
these two calls. And so on.

Finally, the third argument, `char * error_message` serves both as error flag,
and a method to report a human-readable message (if any). UDFs should only raise
that flag/message to indicate *unrecoverable* internal errors; ones that would
prevent any subsequent attempts to evaluate that instance of the UDF call from
continuing.

You must *not* use this flag for argument type checks, or for any other error
reporting that is likely to happen during "normal" use. This flag is designed to
report sudden critical runtime errors only, such as running out of memory.

If we need to, say, allocate temporary storage for our function to use, or check
upfront whether the arguments are of the supported types, then we need to add
two more functions, with UDF initialization and deinitialization, respectively.

```c
int testfunc_init(SPH_UDF_INIT * init, SPH_UDF_ARGS * args,
    char * error_message)
{
    // allocate and initialize a little bit of temporary storage
    init->func_data = malloc(sizeof(int));
    *(int*)init->func_data = 123;

    // return a success code
    return 0;
}

void testfunc_deinit(SPH_UDF_INIT * init)
{
    // free up our temporary storage
    free(init->func_data);
}
```

Note how `testfunc_init()` also receives the call arguments structure. At that
point in time we do not yet have any actual per-row *values* though, so the
`args->arg_values` will be `NULL`. But the argument names and types are already
known, and will be passed. You can check them in the initialization function and
return an error if they are of an unsupported type.

### UDF argument and return types

UDFs can receive arguments of pretty much any valid internal Sphinx type. When
in doubt, refer to `sphinx_udf_argtype` enum in `sphinxudf.h` for a full list.
For convenience, here's a short reference table:

| UDF arg type | C/C++ type, and a short description                    | Len |
|--------------|--------------------------------------------------------|-----|
| UINT32       | `uint32_t`, unsigned 32-bit integer                    | -   |
| INT64        | `int64_t`, signed 64-bit integer                       | -   |
| FLOAT        | `float`, single-precision (32-bit) IEEE754 float       | -   |
| STRING       | `char *`, non-ASCIIZ string, with a separate length    | Yes |
| UINT32SET    | `uint32_t *`, sorted set of u32 integers               | Yes |
| INT64SET     | `int64_t *`, sorted set of i64 integers                | Yes |
| FACTORS      | `void *`, special blob with ranking signals            | -   |
| JSON         | `char *`, JSON (sub)object or field in a string format | -   |
| FLOAT_VEC    | `float *`, an unsorted array of floats                 | Yes |

The `Len` column in this table means that the argument length is passed
separately via `args->str_lengths[i]` in addition to the argument value
`args->arg_values[i]` itself.

For `STRING` arguments, the length contains the string length, in bytes. For all
other types, it contains the number of elements.

As for the return types, UDFs can currently return numeric or string values.
The respective types are as follows:

| Sphinx type | Regular return type | Batched output arg type |
|-------------|---------------------|-------------------------|
| `UINT`      | `sphinx_int64_t`    | `int *`                 |
| `BIGINT`    | `sphinx_int64_t`    | `sphinx_int64_t *`      |
| `FLOAT`     | `double`            | `float *`               |
| `STRING`    | `char *`            | -                       |

Batched calls are discussed below.

We still define our own `sphinx_int64_t` type in `sphinxudf.h` for clarity and
convenience, but these days, any standard 64-bit integer type like `int64_t` or
`long long` should also suffice, and can be safely used in your UDF code.

Any non-scalar return values in general (for now just the `STRING` return type)
**MUST** be allocated using `args->fn_malloc` function.

Also, `STRING` values must (rather naturally) be zero-terminated C/C++ strings,
or the engine will crash.

It is safe to return a `NULL` value. At the moment (as of v.3.4), that should be
equivalent to returning an empty string.

Of course, *internally* in your UDF you can use whatever allocator you want, so
the `testfunc_init()` example above is correct even though it uses `malloc()`
directly. You manage that pointer yourself, it gets freed up using a matching
`free()` call, and all is well. However, the *returned* strings values will be
managed by Sphinx, and we have our own allocator. So for the return values
specifically, you need to use it too.

Note than when you set a non-empty error message, the engine will immediately
free the pointer that you return. So even in the error case, you still *must*
either return whatever you allocated with `args->fn_malloc` (otherwise that
would be a leak). However, in this case it's okay to return a garbage buffer
(eg. not yet fully initialized and therefore not zero-terminated), as the engine
will not attempt to interpret it as a string.

### UDF library initialization

Sphinx v.3.5 adds support for **parametrized UDF library initialization**.

You can now implement `int <LIBRARYNAME>_libinit(const char *)` in your library,
and if that exists, `searchd` will call that function once, immediately after
the library is loaded. This is optional, you are not *required* to implement
this function.

The string parameter passed to `_libinit` is taken from the `plugin_libinit_arg`
directive in the `common` section. You can put any arbitrary string there. The
default `plugin_libinit_arg` value is an empty string.

There will be some macro expansion applied to that string. At the moment, the
only known macro is `$extra` that expands to `<DATADIR>/extra`, where in turn
`<DATADIR>` means the current active datadir path. This is to provide UDFs with
an easy method access to datadir VFS root, where all the resource files must be
stored in the datadir mode.

**The library initialization function can fail.** On success, you must return 0.
On failure, you can return any other code, it will be reported.

To summarize, the library load sequence is as follows.

  - `dlopen()` that implicitly calls any C/C++ global initializers;
  - the mandatory `<LIBNAME>_ver()` call;
  - the optional `<LIBNAME>_libinit(<plugin_libinit_arg>)` call.

### UDF call batching

Since v.3.3 Sphinx supports two types of the "main" UDF call with a numeric
return type:

  * regular, called with exactly 1 row at a time;
  * batched, called with batches of 1 to 128 rows at a time.

These two types have different C/C++ signatures, for example:

```c
/// regular call that RETURNS UINT
/// note the `sphinx_int64_t` ret type
sphinx_int64_t foo(SPH_UDF_INIT * init, SPH_UDF_ARGS * args,
    char * error);

/// batched call that RETURNS UINT
/// note the `int *` out arg type
void foo_batch(SPH_UDF_INIT * init, SPH_UDF_ARGS * args,
    int * results, int batch_size, char * error);
```

UDF must define at least 1 of these two functions. As of v.3.3, UDF can define
both functions, but batched calls take priority. So when both `foo_batch()` and
`foo()` are defined, the engine will only use `foo_batch()`, and completely
ignore `foo()`.

Batched calls are needed for performance. For instance, processing multiple
documents at once with certain CatBoost ML models could be more than 5x faster.

Starting from v.3.5 the engine can also batch the UDF calls when doing no-text
queries too (ie. `SELECT` queries without a `MATCH()` clause). Initially we only
batched them when doing full-text queries.

As mentioned a little earlier, return types for batched calls differ from
regular ones, again for performance reasons. So yes, the types in the example
above are correct. Regular, single-row `foo()` call must use `sphinx_int64_t`
for its return type either when the function was created with `RETURNS UINT` or
`RETURNS BIGINT`, for simplicity. However the batched multi-row `foo_batch()`
call **must** use an output buffer typed as `int *` when created with
`RETURNS UINT`; or a buffer typed as `sphinx_int64_t *` when created with
`RETURNS BIGINT`; just as mentioned in that types table earlier.

Current target batch size is 128, but that size may change in either direction
in the future. Assume little about `batch_size`, and very definitely do *not*
hardcode the current limit anywhere. (Say, it is reasonably safe to assume that
batches will always be in 1 to 65536 range, though.)

Engine should accumulate matches up to the target size, so that most UDF calls
receive complete batches. However, trailing batches will be sized arbitrarily.
For example, for 397 matches there should be 4 calls to `foo_batch()`, with 128,
128, 128, and 13 matches per batch respectively.

Arguments (and their sizes where applicable) are stored into `arg_values` (and
`str_lengths`) sequentially for every match in the batch. For example, you can
access them as follows:

```cpp
for (int row = 0; row < batch_size; row++)
    for (int arg = 0; arg < args->arg_count; arg++)
    {
        int index = row * args->args_count + arg;
        use_arg(args->arg_values[index], args->str_lengths[index]);
    }
```

Batched UDF **must** fill the **entire** results array with some sane default
value, even if it decides to fail with an unrecoverable error in the middle of
the batch. It must never return garbage results.

On error, engine will stop calling the batched UDF for the rest of the current
`SELECT` query (just as it does with regular UDFs), and automatically zero out
the rest of the values. However, it is the UDFs responsbility to completely fill
the failed batch anyway.

Batched calls are currently only supported for numeric UDFs, ie. functions that
return `UINT`, `BIGINT`, or `FLOAT`; batching is not yet supported for `STRING`
functions. That may change in the future.

### Using `FACTORS()` in UDFs

Most of the types map straightforwardly to the respective C types. The most
notable exception is the `SPH_UDF_TYPE_FACTORS` argument type. You get that type
by passing `FACTORS()` expression as an argument to your UDF. The value that the
UDF will receive is a binary blob in a special internal format.

To extract individual ranking signals from that blob, you need to use either of
the two `sphinx_factors_XXX()` or `sphinx_get_YYY_factor()` function families.

The first family consists of just 3 functions:

  * `sphinx_factors_init()` that initializes the unpacked `SPH_UDF_FACTORS`
     structure;
  * `sphinx_factors_unpack()` that unpacks a binary blob value into it;
  * `sphinx_factors_deinit()` that cleans up an deallocates `SPH_UDF_FACTORS`.

So you need to call `init()` and `unpack()` first, then you can use the fields
within the `SPH_UDF_FACTORS` structure, and then you have to call `deinit()` for
cleanup. The resulting code would be rather simple, like this:

```c
// init!
SPH_UDF_FACTORS F;
sphinx_factors_init(&F);

if (sphinx_factors_unpack((const unsigned int *)args->arg_values[0], &F))
{
    sphinx_factors_deinit(&F); // no leaks please
    return -1;
}

// process!
int result = F.field[3].hit_count;
// ... maybe more math here ...

// cleanup!
sphinx_factors_deinit(&F);
return result;
```

However, this access simplicity has an obvious drawback. It will cause several
memory allocations per each processed document (made by `init()` and `unpack()`
and later freed by `deinit()` respectively), which might be slow.

So there is another interface to access `FACTORS()` that consists of a bunch of
`sphinx_get_YYY_factor()` functions. It is more verbose, but it accesses the
blob data directly, and it *guarantees* zero allocations and zero copying. So
for top-notch ranking UDF performance, you want that one. Here goes the matching
example code that also accesses just 1 signal from just 1 field:

```c
// init!
const unsigned int * F = (const unsigned int *)args->arg_values[0];
const unsigned int * field3 = sphinx_get_field_factors(F, 3);

// process!
int result = sphinx_get_field_factor_int(field3, SPH_FIELDF_HIT_COUNT);
// ... maybe more math here ...

// done! no cleanup needed
return result;
```

### UDF calls sequences

Depending on how your UDFs are used in the query, the main function call
(`testfunc()` in our running example) might get called in a rather different
volume and order. Specifically,

   * UDFs referenced in `WHERE`, `ORDER BY`, or `GROUP BY` clauses must and will
     be evaluated for every matched document. They will be called in the
     **natural matching order**.

   * without subselects, UDFs that can be evaluated at the very last stage over
     the final result set will be evaluated that way, but before applying the
     `LIMIT` clause. They will be called in the **result set order**.

   * with subselects, such UDFs will also be evaluated *after* applying the
     inner `LIMIT` clause.

The calling sequence of the other functions is fixed, though. Namely,

   * `testfunc_init()` is called once when initializing the query. It can return
     a non-zero code to indicate a failure; in that case query gets terminated
     early, and the error message from the `error_message` buffer is returned.

   * `testfunc()` or `testfunc_batch()` is called for every eligible row batch
     (see above), whenever Sphinx needs to compute the UDF value(s). This call
     can indicate an unrecoverable error by writing either a value of 1, or some
     human-readable message to the `error_message` argument. (So in other words,
     you can use `error_message` either as a boolean flag, or a string buffer.)

   * After getting a non-zero `error_message` from the main UDF call, the engine
     guarantees to stop calling that UDF call for subsequent rows for the rest
     of the query. A default return value of 0 for numerics and an empty string
     for strings will be used instead. Sphinx might or might not choose to
     terminate such queries early, neither behavior is currently guaranteed.

   * `testfunc_deinit()` is called once when the query processing (in a given
     index shard) ends. It must get called even if the main call reported
     an unrecoverable error earlier.


Using table functions
----------------------

Table functions take an arbitrary result set as their input, and return a new, 
processed, (completely) different one as their output.

First argument must always be the input result set, but a table function can
optionally take and handle more arguments. As for syntax, it must be a `SELECT`
**in extra round braces**, as follows. Regular and nested SELECTs are both ok.

```sql
# regular select in a tablefunc
SELECT SOMETABLEFUNC(
  (SELECT * FROM mytest LIMIT 30),
  ...)

# nested select in a tablefunc
SELECT SOMETABLEFUNC(
  (SELECT * FROM
    (SELECT * FROM mytest ORDER BY price ASC LIMIT 500)
    ORDER BY WEIGHT() DESC LIMIT 100),
  ...)
```

Table function can completely change the result set including the schema. Only
built-in table functions are supported for now. (UDFs are quite viable here, but
all these years the demand ain't been great.)


### REMOVE_REPEATS table function

```sql
SELECT REMOVE_REPEATS(result_set, column) [LIMIT [<offset>,] <row_count>]
```

This function removes all `result_set` rows that have the same `column` value as
in the previous row. Then it applies the `LIMIT` clause (if any) to the newly
filtered result set.

### PESSIMIZE_RANK table function

```sql
SELECT PESSIMIZE_RANK(result_set, key_column, rank_column, base_coeff,
    rank_fraction) [LIMIT [<offset>,] <row_count>]

# example
SELECT PESSIMIZE_RANK((SELECT user_id, rank FROM mytable LIMIT 500),
    user_id, rank, 0.95, 1) LIMIT 100
```

This function gradually pessimizes `rank_column` values when several result set
rows share the same `key_column` value. Then it reorders the entire set by newly
pessimized `rank_column` value, and finally applies the `LIMIT` clause, if any.

In the example above it decreases `rank` (more and more) starting from the 2nd
input result set row with the same `user_id`, ie. from the same user. Then it
reorders by `rank` again, and returns top 100 rows by the pessimized rank.

Paging with non-zero offsets is also legal, eg. `LIMIT 40, 20` instead of
`LIMIT 100` would skip first 40 rows and then return 20 rows, aka page number 3
with 20 rows per page.

The specific pessimization formula is as follows. Basically, `base_coeff`
controls the exponential decay power, and `rank_fraction` controls the lerp
power between the original and decayed `rank_column` values.

```
pessimized_part = rank * rank_fraction * pow(base_coeff, prev_occurrences)
unchanged_part  = rank * (1 - rank_fraction)
rank            = pessimized_part + unchanged_part
```

`prev_occurrences` is the number of rows with the matching `key_column` value
that precede the current row in the input result set. It follows that the result
set is completely untouched when all `key_column` values are unique.

`PESSIMIZE_RANK()` also forbids non-zero offsets in argument `SELECT` queries,
meaning that `(SELECT * FROM mytable LIMIT 10)` is ok, but `(... LIMIT 30, 10)`
must fail. Because the pessimization is position dependent. And applying it to
an arbitrarily offset slice (rather than top rows) is kinda sorta meaningless.
Or in other words, with pessimization, `LIMIT` paging is only allowed outside of
`PESSIMIZE_RANK()` and forbidden inside it.


Using datadir
--------------

Starting with v.3.5 we are actively converting to **datadir mode** that unifies
Sphinx data files layout. Legacy non-datadir configs are still supported as of
v.3.5, but that support is slated for removal. You should convert ASAP.

The key changes that the datadir mode introduces are as follows.

  1. Sphinx now keeps all its data files in a single "datadir" folder.
  2. Most (or all) of the configurable paths are now deprecated.
  3. Most (or all) the "resource" files must now be referenced by name only.

**"Data files" include pretty much everything**, except perhaps `.conf` files.
Completely everything! Both Sphinx data files (ie. FT indexes, binlogs, searchd
logs, query logs, etc), *and* custom user "resource" files (ie. stopwords,
mappings, morphdicts, lemmatizer dictionaries, global IDFs, UDF binaries, etc)
*must* now all be placed in datadir.

**The default datadir name is `./sphinxdata`**, however, you can (and really
*should*!) specify some non-default location instead. Either with a `datadir`
directive in the `common` section of your config file, or using the `--datadir`
CLI switch. It's prudent to use *absolute* paths rather than relative ones, too.

**The CLI switch takes priority over the config.** Makes working with multiple
instances easier.

**Datadirs are designed to be location-agnostic.** Moving the entire Sphinx
instance *must* be as simple as moving the datadir (and maybe the config), and
changing that single `datadir` config directive.

**Internal datadir folder layout is now predefined.** For reference, there are
the following subfolders.

| Folder    | Contents                                              |
|-----------|-------------------------------------------------------|
| `binlogs` | Per-index WAL files                                   |
| `extra`   | User resource files, with **unique** filenames        |
| `indexes` | FT indexes, one `indexes/<NAME>/` subfolder per index |
| `logs`    | Logs, ie. `searchd.log`, `query.log`, etc             |
| `plugins` | User UDF binaries, ie. the `.so` files                |

There also are a few individual "system" files too, such as PID file, dynamic
state files, etc, currently placed in the root folder.

**Resource files must now be referenced by base file names only.** In datadir
mode, you now ***must*** do the following.

  1. place all your resource files into `$datadir/extra/` folder;
  2. give them unique names (unique within the `extra` folder);
  3. refer to those files (from config directives) by name only.

Very briefly, you now must use names only, like `stopwords = mystops.txt`, and
you now must place that `mystops.txt` anywhere within the `extra/` folder. For
more details see ["Migrating to datadir"](#migrating-to-datadir).

**Any subfolder structure within `extra` is intentionally ignored.** This lets
you very easily rearrange the resource files whenever and however you find
convenient. This is also one of the reasons why the names must be unique.

**Logs and binlogs are now stored in a fixed location; still can be disabled.**
They are enabled by default, with `query_log_min_msec = 1000` threshold for the
query log. However, you can still disable them. For binlogs, there now is a new
`binlog` directive for that.

  - `log =` (no value) or `log = no` disables the daemon log (NOT recommended!);
  - `query_log =` or `query_log = no` disables the query log;
  - `binlog = 0` disables all binlogs, ie. WALs.


Migrating to datadir
---------------------

Legacy non-datadir configs are still supported in v.3.5. However, that support
just *might* get dropped as soon as in v.3.6. So you should convert ASAP.

Once you add a datadir directive, your config becomes subject to extra checks,
and your files layout changes. Here's a little extra info on how to upgrade.

**The index `path` is now deprecated!** Index data files are now automatically
placed into "their" respective folders, following the `$datadir/indexes/$name/`
pattern, where `$name` is the index name. And the `path` directives must now be
removed from the datadir-mode configs.

**The index format is still generally backwards compatible.** Meaning that you
may be able to simply move the older index files "into" the new layout. Those
should load and work okay, save for a few warnings to convert to basenames.
However, non-unique resource files names may prevent that, see below.

**Resource files should be migrated, and their names should be made unique**.
This is probably best explained with an example. Assume that you had `stopwords`
and `mappings` for index `test1` configured as follows.

```bash
index test1
{
    ...
    stopwords = /home/sphinx/morph/stopwords/test1.txt
    mappings = /home/sphinx/morph/mappings/test1.txt
}
```

Assume that you placed your datadir at `/home/sphinx/sphinxdata` when upgrading.
You should then move these resource files into `extra`, assign them unique names
along the way, and update the config respectively.

```bash
cd /home/sphinx
mkdir sphinxdata/extra/stopwords
mkdir sphinxdata/extra/mappings
mv morph/stopwords/test1.txt sphinxdata/extra/stopwords/test1stops.txt
mv morph/mappings/test1.txt sphinxdata/extra/mappings/test1maps.txt
```

```bash
index test1
{
    ...
    stopwords = test1stops.txt
    mappings = test1maps.txt
}
```

**Note that non-unique resource files names might be embedded in your indexes.**
Alas, in that case you'll have to rebuild your indexes. Because once you switch
to datadir, Sphinx can no longer differentiate between the two `test1.txt` base
names, you gotta be more specific that that.

**A few config directives "with paths" should be updated.** These include `log`,
`query_log`, `binlog_path`, `pid_file`, `lemmatizer_base`, and `sphinxql_state`
directives. The easiest and recommended way is to rely on the current defaults,
and simply remove all these directives. As for lemmatizer dictionary files (ie.
the `.pak` files), those should now placed anywhere in the `extra` folder.

Last but not least, **BACKUP YOUR INDEXES**.


Indexing: data sources
-----------------------

Data that `indexer` (the ETL tool) grabs and indexes must come from somewhere,
and we call that "somewhere" a **data source**.

Sphinx supports 10 different source types that fall into 3 major kinds:

  - SQL sources (`mysql`, `pgsql`, `odbc`, and `mssql`),
  - pipe sources (`csvpipe`, `tsvpipe`, and `xmlpipe2`), and
  - join sources (`tsvjoin`, `csvjoin`, `binjoin`).

So every source declaration in Sphinx rather naturally begins with
[a `type` directive](#source-type-directive).

**SQL and pipe sources are the primary data sources.** At least one of those is
required in every `indexer`-indexed index (sorry, just could not resist).

**Join sources are secondary, and optional.** They basically enable joins across
different systems, performed on `indexer` side. For instance, think of joining
MySQL query result against a CSV file. We discuss them below.

**All per-source directives depend on the source type.** That is even reflected
in their names. For example, `tsvpipe_header` is not legal for `mysql` source
type. (However, the current behavior still is to simply ignore such directives
rather that to raise errors.)

For the record, the `sql_xxx` directives are legal in all the SQL types, ie.
`mysql`, `pgsql`, `odbc`, and `mssql`.

**The pipe and join types are always supported.** Meaning that support for 
`csvpipe`, `tsvpipe`, `xmlpipe2`, `csvjoin`, `tsvjoin` and `binjoin` types is
always there. It's fully built-in and does not require any external libraries.

**The SQL types require an installed driver.** To access this or that SQL DB,
public Sphinx builds *require* the respective dynamic client library installed.
See the section on [installing SQL drivers](#installing-sql-drivers) for a bit
more details.

**`mssql` source type is currently only available on Windows.** That one uses
the native driver, might be a bit easier to configure and use. But if you have
to run `indexer` on a different platform, you can still access MS SQL too, just
use the `odbc` driver for that.

TODO: discuss ranged SQL indexing, XML fixup, etc.


Indexing: CSV and TSV files
----------------------------

`indexer` supports indexing data in both CSV and TSV formats, via the `csvpipe`
and `tsvpipe` source types, respectively. Here's a brief cheat sheet on the
respective source directives.

  * `csvpipe_command = ...` specifies a command to run (for instance,
    `csvpipe_command = cat mydata.csv` in the simplest case).
  * `csvpipe_header = 1` tells the `indexer` to pick the column list from the
    first row (otherwise, by default, the column list has to be specified in the
    config file).
  * `csvpipe_delimiter` changes the column delimiter to a given character (this
    is `csvpipe` only; `tsvpipe` naturally uses tabs).

When working with TSV, you would use the very same directives, but start them
with `tsvpipe` prefix (ie. `tsvpipe_command`, `tsvpipe_header`, etc).

The first column is currently always treated as `id`, and must be a unique
document identifier.

The first row can either be treated as a named list of columns (when
`csvpipe_header = 1`), or as a first row of actual data. By default it's treated
as data. The column names are trimmed, so a bit of extra whitespace should not
hurt.

`csvpipe_header` affects how CSV input columns are matched to Sphinx attributes
and fields.

With `csvpipe_header = 0` the input file only contains data, and the index
schema (which defines the expect CSV columns order) is taken from the config.
Thus, the order of `attr_XXX` and `field` directives (in the respective index)
is quite important in this case. You have to explicitly declare *all* the fields
and attributes (except the leading `id`), and in *exactly* the same order they
appear in the CSV file. `indexer` will help and warn if there were unmatched or
extraneous columns.

With `csvpipe_header = 1` the input file starts with the column names list, so
the declarations from the config file are only used to set the types. In that
case, the index schema *order* does not matter that much any more. The proper
CSV columns will be found by name alright.

**LEGACY WARNING:** with the deprecated `csvpipe_attr_xxx` schema definition
syntax at the source level *and* `csvpipe_header = 1`, any CSV columns that were
not configured explicitly would get auto-configured as full-text fields. When
migrating such configs to use index level schema definitions, you now *have* to
explicitly list all the fields. For example.

```bash
1.csv:

id, gid, title, content
123, 11, hello world, document number one
124, 12, hello again, document number two

sphinx.conf:

# note how "title" and "content" were implicitly configured as fields
source legacy_csv1
{
    type = csvpipe
    csvpipe_command = cat 1.csv
    csvpipe_header = 1
    csvpipe_attr_uint = gid
}

source csv1
{
    type = csvpipe
    csvpipe_command = cat 1.csv
    csvpipe_header = 1
}

# note how we have to explicitly configure "title" and "content" now
index csv1
{
    source = csv1
    field = title, content
    attr_uint = gid
}
```


Indexing: join sources
-----------------------

Join sources let you do cross-storage pseudo-joins, and augment your primary
data (coming from regular data sources) with additional column values (coming
from join sources).

For example, you might want to create *most* of your FT index from a regular
database, fetching the data using a regular SQL query, but fetch a few columns
from a separate CSV file. Effectively that is a cross-storage, SQL by CSV join.
And that's exactly what join sources do.

Let's take a look at a simple example. It's far-fetched, but should illustrate
the core idea. Assume that for some reason per-product discounts are not stored
in our primary SQL database, but in a separate CSV file, updated once per week.
(Maybe the CEO likes to edit those personally on weekends in Excel, who knows.)
We can then fill a default discount percentage value in our `sql_query`, and
load specific discounts from that CSV using `join_attrs` as follows.

```bash
source products
{
    ...
    sql_query = SELECT id, title, price, 50 AS discount FROM products
}

source join_discounts
{
    type = csvjoin
    join_file = discounts.csv
    join_schema = bigint id, uint discount
}

index products
{
    ...
    source = products  
    source = join_discounts

    field_string = title
    attr_uint = price
    attr_uint = discount

    join_attrs = discount
}
```

The `discount` value will now be either 50 by default (as in `sql_query`), or
whatever was specified in `discounts.csv` file.

```bash
$ cat discounts.csv
2181494041,5450
3312929434,6800
3521535453,1300

$ mysql -h0 -P9306 -e "SELECT * FROM products"
+------------+-----------------------------------------+-------+----------+
| id         | title                                   | price | discount |
+------------+-----------------------------------------+-------+----------+
| 2643432049 | Logitech M171 Wireless Mouse            |  3900 |       50 |
| 2181494041 | Razer DeathAdder Essential Gaming Mouse | 12900 |     5450 |
| 3353405378 | HP S1000 Plus Silent USB Mouse          |  2480 |       50 |
| 3312929434 | Apple Magic Mouse                       | 32900 |     6800 |
| 4034510058 | Logitech M330 Silent Plus               |  6700 |       50 |
+------------+-----------------------------------------+-------+----------+
```

So the two lines from `discounts.csv` that mentioned existing product IDs got
joined and did override the default `discount`, the third line that mentioned
some non-existing ID got ignored, and products not mentioned were not affected.
Everything as expected.

But why not just import that CSV into our database, and then do an extra `JOIN`
(with a side of `COALESCE`) in `sql_query`? Two reasons.

First, optimization. Having `indexer` do these joins instead of the primary
database can offload the latter quite significantly. For the record, this was
exactly our own main rationale initially.

Second, simplification. Primary data source isn't even necessarily a database.
It might be file-based itself.

At the moment, we support joins against CSV or TSV files with the respective
`csvjoin` and `tsvjoin` types, or against binary files with the `binjoin` type.
More join source types (and input formats) might come in the future.

There are no restrictions imposed on the primary sources. Note that join sources
are secondary, meaning that at least one primary source is still required.

Join sources currently support (and need) just 3 directives:

  * `join_file = <FILE>` specifies the input data file;
  * `join_header = {0 | 1}` specifies if there's a header line;
  * `join_schema = <col_type col_name} [...]` defines the input data schema.

For example!

```bash
source joined
{
    type = csvjoin
    join_file = joined.csv
    join_header = 1
    join_schema = bigint id, float score, uint price, bigint uid
}

# joined.csv:
#
# id,score,price,uid
# 1,12.3,4567,89
# 100500,3.141,592,653
```

`join_file` and `join_schema` are required. There must always be data to join.
We must always know what exactly to process.

The expected `join_file` format depends on the specific join source type. You
can either use text formats (CSV or TSV), or a simple raw binary format (more
details on that below).

For text formats, CSV/TSV parser is rather limited (for performance reasons),
so quotes and newlines are not supported. Numbers and spaces are generally fine.
When parsing arrays, always-allowed separator is space, and in TSV you can also
use commas (naturally, without quotes you can't use those in CSV).

`join_header` is optional, and defaults to 0. When set to 1, `indexer` parses
the first `join_file` line as a list of columns, and checks that vs the schema.

`join_schema` must contain the input schema, that is, a comma-separated list of
`<type> <column>` pairs that fully describes all input columns.

The first column must always be typed `bigint` and contain the document ID.
Joining will happen based on those IDs. The column name is used for validation
in `join_header = 1` case only, and with `join_header = 0` it is ignored.

The schema is required to contain 2 or more entries, because one ID column, and
at least one data column that we are going to join.

To reiterate, the schema must list **all** the columns from `join_file`, and in
proper order.

Note that you can later choose to only join in **some** (not all!) columns from
`join_file` into your index. `join_attrs` directive in the index (we discuss it
below) lets you do that. But that's for the particular **index** to decide, and
at a later stage. Here, at the source stage, `join_schema` must just list all
the expected **input** columns.

The supported types include numerics and arrays: `bigint`, `float`, and `uint`
for numerics, and `float_array`, `int_array`, and `int8_array` for fixed-width
arrays. Array dimensions syntax is `float_array name[10]` as usual.

Non-ID column names (ie. except the first column) must be unique across all join
sources used in any given index.

To summarize, join sources just quickly configure the input file and its schema,
and that's it.

Now that we covered schemas and types and such, let's get back to `binjoin` type
and its input formats. Basically, `join_schema` directly defines that, too.

With `binjoin` type Sphinx requires *two* binary input files. You must extract
and store all the document IDs separately in `join_ids`, and all the *other*
columns from `join_schema` separately in `join_file`, row by row. Columns in
each `join_file` row must be exactly in `join_schema` order.

All values must be in native binary, so integers must be in low-endian byte
order, floats must be in IEEE-754, no suprises there. Speaking of which, there
is no implicit padding either. Whatever you specify in `join_schema` must get
written into `join_file` exactly as is.

`indexer` infers the joined rows count from `join_ids` size, so that must be
divisible by 8, because `BIGINT` is 8 bytes. `indexer` also checks the expected
`join_file` size too.

Let's dissect a small example. Assume that we have the following 3 rows to join.

```csv
id, score, year
2345, 3.14, 2022
7890, 2.718, 2023
123, 1.0, 2020
```

Assume that `score` is `float` and that `year` is `uint`, as per this schema.

```bash
source binjoin1
{
    type = binjoin
    join_ids = ids.bin
    join_file = rows.bin
    join_schema = bigint id, float score, uint year
}
```

How would that data look in binary? Well, it begins with 24-byte docids file,
with 8 bytes per each document ID.

```python
import struct
with open('ids.bin', 'wb+') as fp:
    fp.write(struct.pack('qqq', 2345, 7890, 123))
```

```bash
$ xxd -c8 -g1 -u ids.bin
00000000: 29 09 00 00 00 00 00 00  ).......
00000008: D2 1E 00 00 00 00 00 00  ........
00000010: 7B 00 00 00 00 00 00 00  {.......
```

The rows data file in this example must also have 8 bytes per row, with 4 bytes
for score and 4 more for year.

```python
import struct
with open('rows.bin', 'wb+') as fp:
    fp.write(struct.pack('fififi', 3.14, 2022, 2.718, 2023, 1.0, 2020))
```

```bash
$ xxd -c8 -g1 -u rows.bin
00000000: C3 F5 48 40 E6 07 00 00  ..H@....
00000008: B6 F3 2D 40 E7 07 00 00  ..-@....
00000010: 00 00 80 3F E4 07 00 00  ...?....
```

Let's visually check the second row. It starts at offset 8 in both our files.
Document ID from `ids.bin` is 0x1ED2 hex, year from `rows.bin` is 0x7E7 hex,
that's 7890 and 2023 in decimal, alright! Everything computes.

Arrays are also allowed with `binjoin` sources. (And more than that, arrays
actually are a primary objective for binary format. Because it saves especially
much on bigger arrays.)

```bash
source binjoin2
{
    type = csvjoin
    join_ids = ids.bin
    join_file = data.bin
    join_schema = bigint id, float score, float_array embeddings[100]
}
```

But why do all these `binjoin` hoops? Performance, performance, performance.
**When your data is already binary** in the first place, shipping it as binary
is somewhat faster (and likely easier to implement too). With `binjoin` we fully
eliminate text formatting step on the data source side and text parsing step on
Sphinx side. Those steps are *very* noticeable when processing millions of rows!
Of course, if your data is in text format, then either CSV or TSV are fine.

Actual joins are then performed by FT indexes, based on the join source(s) added
to the index using the `source` directive, and on `join_attrs` setup. Example!

```bash
index jointest
{
   ...
    source = primarydb
    source = joined

    field = title
    attr_uint = price
    attr_bigint = ts
    attr_float = weight

    join_attrs = ts:ts, weight:score, price
}
```

Compared to a regular index, we added just 2 lines: `source = joined` to define
the source of our joined data, and `join_attrs` to define which index columns
need to be populated with which joined columns.

Multiple join sources may be specified per one index. Every source is expected
to have its own unique columns names. In the example above, `price` column name
is now taken by `joined` source, so if we add another `joined2` source, none of
its columns can be called `price` any more.

`join_attrs` is a comma-separated list of `index_attr:joined_column` pairs that
binds target index attributes to source joined columns, by their names.

Index attribute name and joined column name are **not** required to match. Note
how the `score` column from CSV gets mapped to `weight` in the index.

But they **can** match. When they do, the joined column name can be skipped for
brevity. That's what happens with the `price` bit. Full blown `price:price` is
still legal syntax too, of course.

Since joined column names must be unique across all join sources, we don't have
to have source names in `join_attrs`, the (unique) joined column names suffice.

Types are checked and must match perfectly. You can't join neither int to string
nor float to int. Array types and dimensions must match perfectly too.

All column names are case-insensitive.

A single join source is currently limited to at most 1 billion rows.

First entry with a given document ID seen in the join source wins, subsequent
entries with the same ID are ignored.

Non-empty data files are **required** by default. If missing or empty data files
are not an error, use `join_optional = 1` directive to explicitly allow that.

Last but not least, note that joins might eat a huge lot of RAM!

In the current implementation `indexer` fully parses all the join sources
upfront (before fetching any row data), then keeps all parsed data in RAM,
completely irregardless of the `mem_limit` setting.

This implementation is an intentional tradeoff, for simplicity and performance,
given that in the end all the attributes (including the joined ones) are anyway
expected to more or less fit into RAM.

However, this also means that you can't expect to efficiently join a huge 100 GB
CSV file into a tiny 1 million row index on a puny 32 GB server. (Well, it might
even work, but definitely with a lot of swapping and screaming.) Caveat emptor.

Except, note that in `binjoin` sources this "parsed data" means `join_ids` only!
Row data stored in `join_file` is already binary, no parsing step needed there,
so `join_file` just gets memory-mapped and then used directly.

So `binjoin` sources are more RAM efficient. Because in `csvjoin` and `tsvjoin`
types the entire text `join_file` *has* to be parsed and stored in RAM, and that
step does not exist in `binjoin` sources. On the other hand, (semi) random reads
from mapped `join_file` might be heavier on IO. Caveat emptor iterum.


Indexing: special chars, blended tokens, and mixed codes
---------------------------------------------------------

Sphinx provides tools to help you better index (and then later search):

  * terms that have special characters in them, like `@Rihanna`,
    or `Procter&Gamble` or `U.S.A`, etc;
  * terms that mix letters and digits, like `UE53N5740AU`.

The general approach, so-called "blending", is the same in both cases:

  * we always store a certain "base" (most granular) tokenization;
  * we also additionally store ("blend") extra tokens, as configured;
  * we then let you search for either original or extra tokens.

So in the examples just above Sphinx can:

  * index base tokens, such as `rihanna` or `ue53n5740au`;
  * index special tokens, such as `@rihanna`;
  * index parts of mixed-codes tokens, such as `ue 53` and `ue53`.

### Blended tokens (with special characters)

To index **blended tokens**, ie. tokens with special characters in them,
you should:

  * add your special "blended" characters to the `blend_chars` directive;
  * configure several processing modes for the extra tokens (optionally) using
    the `blend_mode` directive;
  * rebuild your index.

Blended characters are going to be indexed both as separators, and *at the same
time* as valid characters. They are considered separators when generating the
base tokenization (or "base split" for short). But in addition they also are
processed as valid characters when generating extra tokens.

For instance, when you set `blend_chars = @, &, .` and index the text `@Rihanna
Procter&Gamble U.S.A`, the base split stores the following six tokens into the
final index: `rihanna`, `procter`, `gamble`, `u`, `s`, and `a`. Exactly like it
would without the `blend_chars`, based on just the `charset_table`.

And because of `blend_chars` settings, the following three *extra* tokens get
stored: `@rihanna`, `procter&gamble`, and `u.s.a`. Regular characters are still
case-folded according to `charset_table`, but those special blended characters
are now preserved. As opposed to being treated as whitespace, like they were in
the base split. So far so good.

But why not just add `@, &, .` to `charset_table` then? Because that way
we would completely lose the base split. *Only* the three "magic" tokens like
`@rihanna` would be stored. And then searching for their "parts" (for example,
for just `rihanna` or just `gamble`) would not work. Meh.

Last but not least, the in-field token positions are adjusted accordingly, and
shared between the base and extra tokens:

  * pos 1, `rihanna` and `@rihanna`
  * pos 2, `procter` and `procter&gamble`
  * pos 3, `gamble`
  * pos 4, `u` and `u.s.a`
  * pos 5, `s`
  * pos 6, `a`

Bottom line, `blend_chars` lets you enrich the index and store extra tokens
with special characters in those. That might be a handy addition to your regular
tokenization based on `charset_table`.

### Mixed codes (with letters and digits)

To index **mixed codes**, ie. terms that mix letters and digits, you need to
enable `blend_mixed_codes = 1` setting (and reindex).

That way Sphinx adds extra spaces on *letter-digit boundaries* when making the
base split, but still stores the full original token as an extra. For example,
`UE53N5740AU` gets broken down to as much as 5 parts:

  * pos 1, `ue` and `ue53n5740au`
  * pos 2, `53`
  * pos 3, `n`
  * pos 4, `5740`
  * pos 5, `au`

Besides the "full" split and the "original" code, it is also possible to store
prefixes and suffixes. See `blend_mode` discussion just below.

Also note that on certain input data mixed codes indexing can generate a lot of
undesired noise tokens. So when you have a number of fields with special terms
that do *not* need to be processed as mixed codes (consider either terms like
`_category1234`, or just long URLs), you can use the `mixed_codes_fields`
directive and limit mixed codes indexing to human-readable text fields only.
For instance:

```bash
blend_mixed_codes = 1
mixed_codes_fields = title, content
```

That could save you a noticeable amount of both index size and indexing time.

### Blending modes

There's somewhat more than one way to generate extra tokens. So there is
a directive to control that. It's called `blend_mode` and it lets you list all
the different processing variants that you require:

  * `trim_none`, store a full token with all the blended characters;
  * `trim_head`, store a token with heading blended characters trimmed;
  * `trim_tail`, store a token with trailing blended characters trimmed;
  * `trim_both`, store a token with both heading and trailing blended
    characters trimmed;
  * `skip_pure`, do *not* store tokens that only contain blended characters;
  * `prefix_tokens`, store all possible prefix tokens;
  * `suffix_tokens`, store all possible suffix tokens.

To visualize all those trims a bit, consider the following setup:
```bash
blend_chars = @, !
blend_mode = trim_none, trim_head, trim_tail, trim_both

doc_title = @someone!
```

Quite a bunch of extra tokens will be indexed in this case:

  * `someone` for the base split;
  * `@someone!` for `trim_none`;
  * `someone!` for `trim_head`;
  * `@someone` for `trim_tail`;
  * `someone` (yes, again) for `trim_both`.

`trim_both` option might seem redundant here for a moment. But do consider
a bit more complicated term like `&U.S.A!` where all the special characters are
blended. It's base split is three tokens (`u`, `s`, and `a`); it's original full
form (stored for `trim_none`) is lower-case `&u.s.a!`; and so for this term
`trim_both` is the only way to still generate the cleaned-up `u.s.a` variant.

`prefix_tokens` and `suffix_tokens` actually begin to generate something
non-trivial on that very same `&U.S.A!` example, too. For the record, that's
because its base split is long enough, 3 or more tokens. `prefix_tokens` would
be the only way to store the (useful) `u.s` prefix; and `suffix_tokens` would
in turn store the (questionable) `s.a` suffix.

But `prefix_tokens` and `suffix_tokens` modes are, of course, especially
useful for indexing mixed codes. The following gets stored with
`blend_mode = prefix_tokens` in our running example:

  * pos 1, `ue`, `ue53`, `ue53n`, `ue53n5740`, and `ue53n5740au`
  * pos 2, `53`
  * pos 3, `n`
  * pos 4, `5740`
  * pos 5, `au`

And with `blend_mode = suffix_tokens` respectively:

  * pos 1, `ue` and `ue53n5740au`
  * pos 2, `53` and `53n5740au`
  * pos 3, `n` and `n5740au`
  * pos 4, `5740` and `5740au`
  * pos 5, `au`

Of course, there still can be missing combinations. For instance, `ue 53n`
query will still not match any of that. However, for now we intentionally
decided to avoid indexing *all* the possible base token subsequences, as that
seemed to produce way too much noise.

### Searching vs blended tokens and mixed codes

The rule of thumb is quite simple. All the extra tokens are **indexing-only**.
And in queries, all tokens are treated "as is".

**Blended characters** are going to be handled as valid characters in the
queries, and *require* matching.

For example, querying for `"@rihanna"` will *not* match `Robyn Rihanna Fenty
is a Barbadian-born singer` document. However, querying for just `rihanna` will
match both that document, and `@rihanna doesn't tweet all that much` document.

**Mixed codes** are *not* going to be automatically "sliced" in the queries.

For example, querying for `UE53` will *not* automatically match neither `UE 53`
nor `UE 37 53` documents. You need to manually add extra whitespace into your
query term for that.


Searching: query syntax
------------------------

By default, full-text queries in Sphinx are treated as simple "bags of words",
and all keywords are required in a document to match. In other words, by default
we perform a strict boolean AND over all keywords.

However, text queries are much more flexible than just that, and Sphinx has its
own full-text query language to expose that flexibility.

You essentially use that language *within* the `MATCH()` clause in your `SELECT`
statements. So in this section, when we refer to just the `hello world` (text)
query for brevity, the actual complete SphinxQL statement that you would run
is something like `SELECT *, WEIGHT() FROM myindex WHERE MATCH('hello world')`.

That said, let's begin with a couple key concepts, and a cheat sheet.


### Operators

Operators generally work on arbitrary subexpressions. For instance, you can
combine keywords using operators AND and OR (and brackets) as needed, and build
any boolean expression that way.

However, there is a number of exceptions. Not all operators are universally
compatible. For instance, phrase operator (double quotes) naturally only works
on keywords. You can't build a "phrase" from arbitrary boolean expressions.

Some of the operators use special characters, like the phrase operator uses
double quotes: `"this is phrase"`. Thus, sometimes you might have to filter out
a few special characters from end-user queries, to avoid unintentionally
triggering those operators.

Other ones are literal, and their syntax is an all-caps keyword. For example,
MAYBE operator would quite literally be used as `(rick MAYBE morty)` in a query.
To avoid triggering those operators, it should be sufficient to lower-case
the query: `rick maybe morty` is again just a regular bag-of-words query that
just requires all 3 keywords to match.


### Modifiers

Modifiers are attached to individual keywords, and they must work at all times,
and must be allowed within any operator. So no compatibility issues there!

A couple examples would be the exact form modifier or the field start modifier,
`=exact ^start`. They limit matching of "their" keyword to either its exact
morphological form, or at the very start of (any) field, respectively.


### Cheat sheet

As of v.3.2, there are just 4 per-keyword modifiers.

| Modifier       | Example      | Description                                           |
|----------------|--------------|-------------------------------------------------------|
| exact form     | `=cats`      | Only match this exact form, needs `index_exact_words` |
| field start    | `^hello`     | Only match at the very start of (any) field           |
| field end      | `world$`     | Only match at the very end of (any) field             |
| IDF boost      | `boost^1.23` | Multiply keyword IDF by a given value when ranking     |

The operators are a bit more interesting!

| Operator       | Example                    | Description                                         |
|----------------|----------------------------|-----------------------------------------------------|
| brackets       | `(one two)`                | Group a subexpression                               |
| AND            | `one two`                  | Match both args                                     |
| OR             | `one | two`                | Match any arg                                       |
| term-OR        | `one || two`               | Match any keyword, and reuse in-query position      |
| NOT            | `one -two`                 | Match 1st arg, but exclude matches of 2nd arg       |
| NOT            | `one !two`                 | Match 1st arg, but exclude matches of 2nd arg       |
| MAYBE          | `one MAYBE two`            | Match 1st arg, but include 2nd arg when ranking     |
| field limit    | `@title one @body two`     | Limit matching to a given field                     |
| fields limit   | `@(title,body) test`       | Limit matching to given fields                      |
| fields limit   | `@!(phone,year) test`      | Limit matching to all but given fields              |
| fields limit   | `@* test`                  | Reset any previous field limits                     |
| position limit | `@title[50] test`          | Limit matching to N first positions in a field      |
| phrase         | `"one two"`                | Match all keywords as an (exact) phrase             |
| phrase         | `"one * * four"`           | Match all keywords as an (exact) phrase             |
| proximity      | `"one two"~3`              | Match all keywords within a proximity window        |
| quorum         | `"uno due tre"/2`          | Match any N out of all keywords                     |
| quorum         | `"uno due tre"/0.7`        | Match any given fraction of all keywords            |
| BEFORE         | `one << two`               | Match args in this specific order only              |
| NEAR           | `one NEAR/3 "two three"`   | Match args in any order within a given distance     |
| SENTENCE       | `one SENTENCE "two three"` | Match args in one sentence; needs `index_sp`        |
| PARAGRAPH      | `one PARAGRAPH two`        | Match args in one paragraph; needs `index_sp`       |
| ZONE           | `ZONE:(h3,h4) one two`     | Match in given zones only; needs `index_zones`      |
| ZONESPAN       | `ZONESPAN:(h3,h4) one two` | Match in contiguous spans only; needs `index_zones` |

Now let's discuss all these modifiers and operators in a bit more detail.


### Keyword modifiers

**Exact form** modifier is only applicable when morphology (ie. either stemming
or lemmatizaion) is enabled. With morphology on, Sphinx searches for normalized
keywords by default. This modifier lets you search for an exact original form.
It requires `index_exact_words` setting to be enabled.

The syntax is `=` at the keyword start.

```
=exact
```

For the sake of an example, assume that English stemming is enabled, ie. that
the index was configured with `morphology = stem_en` setting. Also assume that
we have these three sample documents:

```
id, content
1, run
2, runs
3, running
```

Without `index_exact_words`, only the normalized form, namely `run`, is stored
into the index for every document. Even with the modifier, it is impossible to
differentiate between them.

With `index_exact_words = 1`, both the normalized and original keyword forms are
stored into the index. However, by default the keywords are also normalized when
searching. So a query `runs` will get normalized to `run`, and will still match
all 3 documents.

And finally, with `index_exact_words = 1` and with the exact form modifier,
a query like `=runs` will be able to match just the original form, and return
just the document #2.

For convenience, you can also apply this particular modifier to an entire phrase
operator, and it will propagate down to all keywords.

```
="runs down the hills"
"=runs =down =the =hills"
```

**Field start modifier** makes the keyword match if and only if it occurred at
the very beginning of (any) full-text field. (Technically, it will only match
postings with an in-field position of 1.)

The syntax is `^` at the keyword start, mimicked after regexps.

```
^fieldstart
```

**Field end modifier** makes the keyword match if and only if it occurred at
the very end of (any) full-text field. (Technically, it will only match postings
with a special internal "end-of-field" flag.)

The syntax is `$` at the keyword start, mimicked after regexps.

```
fieldend$
```

**IDF boost modifier** lets you adjust the keyword IDF value (used for ranking),
it multiples the IDF value by a given constant. That affects a number of ranking
factors that build upon the IDF. That in turn also affects default ranking.

The syntax is `^` followed by a scale constant. Scale must be non-negative and
must start with a digit or a dot. Scale can be zero, both `^0` and `^0.0` should
be legal.

```
boostme^1.23
```


### Boolean operators (brackets, AND, OR, NOT)

These let you implement grouping (with brackets) and classic boolean logic.
The respective formal syntax is as follows:

  * brackets: `(expr1)`
  * AND: `expr1 expr2`
  * OR: `expr1 | expr2`
  * NOT: `-expr1` or `!expr1`

Where `expr1` and `expr2` are either keywords, or any other computable text
query expressions. Here go a few query examples showing all of the operators.

```
(shaken !stirred)
"barack obama" (alaska | california | texas | "new york")
one -(two | (three -four))
```

Nothing too exciting to see here. But still there are a few quirks worth a quick
mention. Here they go, in no particular order.

**OR operator precedence is higher than AND.**

In other words, ORs take priority, they are evaluated first, ANDs are then
evaluated on top of ORs. Thus, `looking for cat | dog | mouse` query is
equivalent to `looking for (cat | dog | mouse)`, and *not*
`(looking for cat) | dog | mouse`.

**ANDs are implicit.**

There isn't any explicit syntax for them in Sphinx. Just put two expressions
right next to each other, and that's it.

**No all-caps versions for AND/OR/NOT, those are valid keywords.**

So something like `rick AND morty` is equivalent to `rick and morty`, and both
these queries require all 3 keywords to match, including that literal `and`.

Notice the difference in behavior between this, and, say, `rick MAYBE morty`,
where the syntax for operator MAYBE is that all-caps keyword.

**Field and zone limits affect the entire (sub)expression.**

Meaning that `@title` limit in a `@title hello world` query applies to all
keywords, not just a keyword or expression immediately after the limit operator.
Both keywords in this example would need to match in the `title` field, not only
the first `hello`. An explicit way to write this query, with an explicit field
limit for every keyword, would be `(@title hello) (@title world)`.

**Brackets push and pop field and zone limits.**

For example, `(@title hello) world` query requires `hello` to be matched in
`title` only. But that limit ends on a closing bracket, and `world` can then
match anywhere in the document again. Therefore *this* query is equivalent to
something like `(@title hello) (@* world)`.

Even more curiously, but quite predictably, `@body (@title hello) world` query
would in turn be equivalent to `(@title hello) (@body world)`. The first `@body`
limit gets pushed on an opening bracket, and then restored on a closing one.

Sames rules apply to zones, see `ZONE` and `ZONESPAN` operators below.

**In-query positions in boolean operators are sequential.**

And while those do not affect *matching* (aka text based filtering), they do
noticeably affect *ranking*. For example, even if you splice a phrase with ORs,
a rather important "phrase match degree" ranking factor (the one called 'lcs')
does not change at all, even though matching changes quite a lot:

```sql
mysql> select id, weight(), title from test1
  where match('@title little black dress');
+--------+----------+--------------------+
| id     | weight() | title              |
+--------+----------+--------------------+
| 334757 |     3582 | Little black dress |
+--------+----------+--------------------+
1 row in set (0.01 sec)

mysql> select id, weight(), title from test1
  where match('@title little | black | dress');
+--------+----------+------------------------+
| id     | weight() | title                  |
+--------+----------+------------------------+
| 334757 |     3582 | Little black dress     |
| 420209 |     2549 | Little Black Backpack. |
...
```

So in a sense, everything you construct using brackets and operators still looks
like a single huge "phrase" (bag of words, really) to the ranking code. As if
there were no brackets and no operators.

**Operator NOT is really operator ANDNOT.**

While a query like `-something` technically can be computed, more often than not
such a query is just a programming error. And a potentially expensive one
at that, because an implicit list of *all* the documents in the index could be
quite big. Here go a few examples.

```cpp
// correct query, computable at every level
aaa -(bbb -(ccc ddd))

// non-computable queries
-aaa
aaa | -bbb
```

(On a side note, that might also raise the philosophical question of ranking
documents that contain zero matched keywords; thankfully, from an engineering
perspective it would be extremely easy to brutally cut that Gordian knot by
merely setting the weight to zero, too.)

For that reason, NOT operator requires something computable to its left.
An isolated NOT will raise a query error. In case that you *absolutely* must,
you can append some special magic keyword (something like `__allmydocs`, to your
taste) to all your documents when indexing. Two example non-computable queries
just above would then become:

```
(__allmydocs -aaa)
aaa | (__allmydocs -bbb)
```

**Operator NOT only works at term start.**

In order to trigger, it must be preceded with a whitespace, or a bracket, or
other clear keyword boundary. For instance, `cat-dog` is by default actually
equivalent to merely `cat dog`, while `cat -dog` with a space does apply the
operator NOT to `dog`.


### Phrase operator

Phrase operator uses the de-facto standard double quotes syntax and basically
lets you search for an exact phrase, ie. several keywords in this exact order,
without any gaps between them. For example.

```
"mary had a little lamb"
```

Yep, boring. But of course there is a bit more even to this simple operator.

**Exact form modifier works on the entire operator.** Of course, any modifiers
must work within a phrase, that's what modifiers are all about. But with exact
form modifiers there's extra syntax sugar that lets you apply it to the entire
phrase at once: `="runs down the hills"` form is a bit easier to write than
`"=runs =down =the =hills"`.

**Standalone star "matches" any keyword.** Or rather, they skip that position
when matching the phrase. Text queries do not really work with document texts.
They work with just the specified keywords, and analyze their in-document and
in-query positions. Now, a special star token within a phrase operator will not
actually match anything, it will simply adjust the query position when parsing
the query. So there will be no impact on search performance at all, but the
phrase keyword positions will be shifted. For example.

```
"mary had * * lamb"
```

**Stopwords "match" any keyword.** The very same logic applies to stopwords.
Stopwords are not even stored in the index, so we have nothing to match. But
even on stopwords, we still need adjust both the in-document positions when
indexing, and in-query positions when matching.

This sometimes causes a little counter-intuitive and unexpected (but
inevitable!) matching behavior. Consider the following set of documents:

```
id, content
1, Microsoft Office 2016
2, we are using a lot of software from Microsoft in the office
3, Microsoft opens another office in the UK
```

Assume that `in` and `the` are our only stopwords. What documents would be
matched by the following two phrase queries?

1. `"microsoft office"`
2. `"microsoft in the office"`

Query #1 only matches document #1, no big surprise there. However, as we just
discussed, query #2 is in fact equivalent to `"microsoft * * office"`, because
of stopwords. And so it matches both documents #2 and #3.


### MAYBE operator

Operator MAYBE is occasionally needed for ranking. It takes two arbitrary
expressions, and only requires the first one to match, but uses the (optional)
matches of the second expression for ranking.

```
expr1 MAYBE expr2
```

For instance, `rick MAYBE morty` query matches exactly the same documents as
just `rick`, but with that extra MAYBE, documents that mention both `rick` and
`morty` will get ranked higher.

Arbitrary expressions are supported, so this is also valid:

```
rick MAYBE morty MAYBE (season (one || two || three) -four')
```


### Term-OR operator

Term-OR operator (double pipe) essentially lets you specify "properly ranked"
per-keyword synonyms at query time.

Matching-wise, it just does regular boolean OR over several keywords, but
ranking-wise (and unlike the regular OR operator), it does *not* increment their
in-query positions. That keeps any positional ranking factors intact.

Naturally, it only accepts individual keywords, you can not term-OR a keyword
and a phrase or any other expression. Also, term-OR is currently not supported
within phrase or proximity operators, though that is an interesting possibility.

It should be easiest to illustrate it with a simple example. Assume we are still
searching for that little black dress, as we did in our example on the regular
OR operator.

```sql
mysql> select id, weight(), title from rt
  where match('little black dress');
+------+----------+-----------------------------------------------+
| id   | weight() | title                                         |
+------+----------+-----------------------------------------------+
|    1 |     3566 | little black dress                            |
|    3 |     1566 | huge black/charcoal dress with a little white |
+------+----------+-----------------------------------------------+
2 rows in set (0.00 sec)
```

So far so good. But looks like `charcoal` is a synonym that we could use here.
Let's try to use it using the regular OR operator.

```sql
mysql> select id, weight(), title from rt
  where match('little black|charcoal dress');
+------+----------+-----------------------------------------------+
| id   | weight() | title                                         |
+------+----------+-----------------------------------------------+
|    3 |     3632 | huge black/charcoal dress with a little white |
|    1 |     2566 | little black dress                            |
|    2 |     2566 | little charcoal dress                         |
+------+----------+-----------------------------------------------+
3 rows in set (0.00 sec)
```

Oops, what just happened? We now also match document #2, which is good, but why
is the document #3 ranked so high all of a sudden?

That's because with regular ORs ranking would, basically, look for the entire
query as if without any operators, ie. the ideal phrase match would be not just
`"little black dress"`, but the entire `"little black charcoal dress"` query
with all special operators removed.

There is no such a "perfect" 4 keyword full phrase match in our small test
database. (If there was, it would get top rank.) From the phrase ranking point
of view, the next kinda-best thing to it is the `"black/charcoal dress"` part,
where a 3 keyword subphrase matches the query. And that's why it gets ranked
higher that `"little black dress"`, where the longest common subphrase between
the document and the query is `"little black"`, only 2 keywords long, not 3.

But that's not what we wanted in this case at all; we just wanted to introduce
a synonym for `black`, rather than break ranking! And that's exactly what
term-OR operator is for.

```sql
mysql> select id, weight(), title from rt
  where match('little black||charcoal dress');
+------+----------+-----------------------------------------------+
| id   | weight() | title                                         |
+------+----------+-----------------------------------------------+
|    1 |     3566 | little black dress                            |
|    2 |     3566 | little charcoal dress                         |
|    3 |     2632 | huge black/charcoal dress with a little white |
+------+----------+-----------------------------------------------+
3 rows in set (0.00 sec)
```

Good, ranking is back to expected. Both the original exact match `"little black
dress"` and synonymical `"little charcoal dress"` are now at the top again,
because of a perfect phrase match (which is favored by the default ranker).

Note that while all the examples above revolved around a single positional
factor `lcs` (which is used in the default ranker), there are more positional
factors than just that. See the section on [Ranking factors](#ranking-factors)
for more details.


### Field and position limit operator

Field limit operator limits matching of the subsequent expressions to a given
field, or a set of fields. Field names must exist in the index, otherwise the
query will fail with an error.

There are several syntax forms available.

1. `@field` limits matching to a single given field. This is the simplest form.
`@(field)` is also valid.

2. `@(f1,f2,f3)` limits matching to multiple given fields. Note that the match
might happen just partially in one of the fields. For example, `@(title,body)
hello world` does *not* require that both keywords match in the very same field!
Document like `{"id":123, "title":"hello", "body":"world"}` (pardon my JSON)
does match this query.

3. `@!(f1,f2,f3)` limits matching to all the fields *except* given ones. This
can be useful to avoid matching end-user queries against some internal system
fields, for one. `@!f1` is also valid syntax in case you want to skip just the
one field.

4. `@*` syntax resets any previous limits, and re-enables matching all fields.

In addition, all forms except `@*` can be followed by an optional `[N]` clause,
which limits the matching to `N` first tokens (keywords) within a field. All of
the examples below are valid:

  * `@title[50] test`
  * `@(title,body)[50] test`
  * `@!title[50] test`

To reiterate, field limits are "contained" by brackets, or more formally, any
current limits are stored on an opening bracket, and restored on a closing one.

When in doubt, use `SHOW PLAN` to figure out what limits are actually used:

```sql
mysql> set profiling=1;
  select * from rt where match('(@title[50] hello) world') limit 0;
  show plan \G
...

*************************** 1. row ***************************
Variable: transformed_tree
   Value: AND(
  AND(fields=(title), max_field_pos=50, KEYWORD(hello, querypos=1)),
  AND(KEYWORD(world, querypos=2)))
1 row in set (0.00 sec)
```

We can see that `@title` limit was only applied to `hello`, and reset back to
matching all fields (and positions) on a closing bracket, as expected.


### Proximity and NEAR operators

**Proximity operator** matches all the specified keywords, in any order, and
allows for a number of gaps between those keywords. The formal syntax is as
follows:

```
"keyword1 keyword2 ... keywordM"~N
```

Where `N` has a little weird meaning. It is the allowed number of gaps (other
keywords) that can occur between those `M` specified keywords, but additionally
incremented by 1.

For example, consider a document that reads `"Mary had a little lamb whose
fleece was white as snow"`, and consider two queries: `"lamb fleece mary"~4`,
and `"lamb fleece mary"~5`. We have exactly 4 extra words between `mary`,
`lamb`, and `fleece`, namely those 4 are `had`, `a`, `little`, and `whose`. This
means that the first query with `N = 4` will *not* match, because with `N = 4`
the proximity operator actually allows for 3 gaps only, not 4. And thus the
second example query will match, as with `N = 5` it allows for 4 gaps (plus 1
permutation).

**NEAR operator** is a generalized version of proximity operator. Its syntax is:

```
expr1 NEAR/N expr2
```

Where `N` has the same meaning as in the proximity operator, the number of
allowed gaps plus one. But with NEAR we can use arbitrary expressions, not just
individual keywords.

```
(binary | "red black") NEAR/2 tree
```

Left and right expressions can still match in any order. For example, a query
`progress NEAR/2 bar` would match both these documents:

1. `progress bar`
2. `a bar called Progress`

NEAR is left associative, meaning that `arg1 NEAR/X arg2 NEAR/Y arg3` will be
evaluated as `(arg1 NEAR/X arg2) NEAR/Y arg3`. It has the same (lowest)
precedence as BEFORE.

Note that while with just 2 keywords proximity and NEAR operators are identical
(eg. `"one two"~N` and `one NEAR/N two` should behave exactly the same), with
more keywords that is *not* the case.

Because when you stack multiple keywords with NEAR, then up to `N - 1` gaps are
allowed per *each* keyword in the stack. Consider this example with two stacked
NEAR operators: `one NEAR/3 two NEAR/3 three`. It allows up to 2 gaps between
`one` and `two`, and then for 2 more gaps between `two` and three. That's less
restrictive than the proximity operator with the same N (`"one two three"~3`),
as the proximity operator will only allow 2 gaps total. So a document with
`one aaa two bbb ccc three` text will match the NEAR query, but *not* the
proximity query.

And vice versa, what if we bump the limit in proximity to match the total limit
allowed by all NEARs? We get `"one two three"~5` (4 gaps allowed, plus that
magic 1), so that anything that matches the NEARs variant would also match the
proximity variant. But now a document `one two aaa bbb ccc ddd three` ceases to
match the NEARs, because the gap between `two` and `three` is too big. And now
the proximity operator becomes less restrictive.

Bottom line is, the proximity operator and a stack of NEARs are *not* really
interchangeable, they match a bit different things.


### Quorum operator

Quorum matching operator essentially lets you perform fuzzy matching. It's less
strict than matching all the argument keywords. It will match all documents with
at least N keywords present out of M total specified. Just like with proximity
(or with AND), those N can occur in any order.

```
"keyword1 keyword2 ... keywordM"/N
"keyword1 keyword2 ... keywordM"/fraction
```

For a specific example, `"the world is a wonderful place"/3` will match all
documents that have any 3 of the specified words, or more.

Naturally, N must be less or equal to M. Also, M must be anywhere from 1 to 256
keywords, inclusive. (Even though quorum with just 1 keyword makes little sense,
that is allowed.)

Fraction must be from 0.0 to 1.0, more details below.

Quorum with `N = 1` is effectively equivalent to a stack of ORs, and can be used
as syntax sugar to replace that. For instance, these two queries are equivalent:

```
red | orange | yellow | green | blue | indigo | violet
"red orange yellow green blue indigo violet"/1
```

Instead of an absolute number `N`, you can also specify a fraction, a floating
point number between 0.0 and 1.0. In this case Sphinx will automatically compute
`N` based on the number of keywords in the operator. This is useful when you
don't or can't know the keyword count in advance. The example above can be
rewritten as `"the world is a wonderful place"/0.5`, meaning that we want to
match at least 50% of the keywords. As there are 6 words in this query, the
autocomputed match threshold would also be 3.

Fractional threshold is rounded up. So with 3 keywords and a fraction of 0.5
we would get a final threshold of 2 keywords, as `3 * 0.5 = 1.5` rounds up as 2.
There's also a lower safety limit of 1 keyword, as matching zero keywords makes
zero sense.

When the quorum threshold is too restrictive (ie. when N is greater than M),
the operator gets automatically replaced with an AND operator. The same fallback
happens when there are more than 256 keywords.


### Strict order operator (BEFORE)

This operator enforces a strict "left to right" order (ie. the query order) on
its arguments. The arguments can be arbitrary expressions. The syntax is `<<`,
and there is no all-caps version.

```
expr1 << expr2
```

For instance, `black << cat` query will match a `black and white cat` document
but *not* a `that cat was black` document.

Strict order operator has the lowest priority, same as NEAR operator.

It can be applied both to just keywords and more complex expressions,
so the following is a valid query:

```
(bag of words) << "exact phrase" << red|green|blue
```


### SENTENCE and PARAGRAPH operators

These operators match the document when both their arguments are within the
same sentence or the same paragraph of text, respectively. The arguments can be
either keywords, or phrases, or the instances of the same operator. (That is,
you can stack several SENTENCE operators or PARAGRAPH operators. Mixing them
is however not supported.) Here are a few examples:

```
one SENTENCE two
one SENTENCE "two three"
one SENTENCE "two three" SENTENCE four
```

The order of the arguments within the sentence or paragraph does not matter.

These operators require indexes built with [`index_sp`](sphinx2.html#conf-index-sp)
setting (sentence and paragraph indexing feature) enabled, and revert to a mere
AND otherwise. You can refer to documentation on `index_sp` for additional
details on what's considered a sentence or a paragraph.


### ZONE and ZONESPAN operators

Zone limit operator is a bit similar to field limit operator, but restricts
matching to a given in-field zone (or a list of zones). The following syntax
variants are supported:

```
ZONE:h1 test
ZONE:(h2,h3) test
ZONESPAN:h1 test
ZONESPAN:(h2,h3) test
```

Zones are named regions within a field. Essentially they map to HTML (or XML)
markup. Everything between `<h1>` and `</h1>` is in a zone called `h1` and could
be matched by that `ZONE:h1 test` query.

Note that ZONE and ZONESPAN limits will get reset not only on a closing bracket,
or on the next zone limit operator, but on a next *field* limit operator too!
So make sure to specify zones explicitly for every field. Also, this makes
operator `@*` a *full* reset, ie. it should reset both field and zone limits.

Zone limits require indexes built with zones support (see documentation on
[`index_zones`](sphinx2.html#conf-index-zones) for a bit more details).

The difference between ZONE and ZONESPAN limit is that the former allows its
arguments to match in multiple disconnected spans of the same zone, and the
latter requires that all matching occurs within a single contiguous span.

For instance, `(ZONE:th hello world)` query *will* match this example document.

```html
<th>Table 1. Local awareness of Hello Kitty brand.</th>
.. some table data goes here ..
<th>Table 2. World-wide brand awareness.</th>
```

In this example we have 2 spans of `th` zone, `hello` will match in the first
one, and `world` in the second one. So in a sense ZONE works on a concatenation
of all the zone spans.

And if you need to further limit matching to any of the individual contiguous
spans, you should use the ZONESPAN operator. `(ZONESPAN:th hello world)` query
does *not* match the document above. `(ZONESPAN:th hello kitty)` however does!


Searching: geosearches
-----------------------

Efficient geosearches are possible with Sphinx, and the related features are:

  * [`GEODIST()` function](#geodist-function) that computes a distance between
    two geopoints
  * [`MINGEODIST()` function](#mingeodist-function) that computes a minimum
    1-to-N points geodistance
  * [`MINGEODISTEX()` function](#mingeodistex-function) that does the same, but
    additionally returns the nearest point's index
  * [`CONTAINS()` function](#contains-function) that checks if a geopoint is
    inside a geopolygon
  * [`CONTAINSANY()` function](#containsany-function) that checks if any of the
    points are inside a geopolygon
  * [attribute indexes](#using-attribute-indexes) that enable speeding up
    `GEODIST()` searches (they are used for fast, early distance checks)
  * special [`MULTIGEO()` attribute index variant](#multigeo-support) that
    enables speeding up `MINGEODST()` searches

### Attribute indexes for geosearches

When you create indexes on your latitude and longitude columns (and you should),
query optimizer can utilize those in a few important `GEODIST()` usecases:

1. Single constant anchor case:
```sql
SELECT GEODIST(lat, lon, $lat, $lon) dist ...
WHERE dist <= $radius
```

2. Multiple constant anchors case:
```sql
SELECT
  GEODIST(lat, lon, $lat1, $lon1) dist1,
  GEODIST(lat, lon, $lat2, $lon2) dist2,
  GEODIST(lat, lon, $lat3, $lon3) dist3,
  ...,
  (dist1 < $radius1 OR dist2 < $radius2 OR dist3 < $radius3 ...) ok
WHERE ok=1
```

These cases are known to the query optimizer, and once it detects them, it can
choose to perform an approximate attribute index read (or reads) first, instead
of scanning the entire index. When the quick approximate read is selective
enough, which frequently happens with small enough search distances, savings
can be huge.

Case #1 handles your typical "give me everything close enough to a certain
point" search. When the anchor point and radius are all constant, Sphinx will
automatically precompute a bounding box that fully covers a "circle" with
a required radius around that anchor point, ie. find some two internal min/max
values for latitude and longitude, respectively. It will then quickly check
attribute indexes statistics, and if the bounding box condition is selective
enough, it will switch to attribute index reads instead of a full scan.

Here's a working query example:

```sql
SELECT *, GEODIST(lat,lon,55.7540,37.6206,{in=deg,out=km}) AS dist
FROM myindex WHERE dist<=100
```

Case #2 handles multi-anchor search, ie. "give me documents that are either
close enough to point number 1, or to point number 2, etc". The base approach
is exactly the same, but *multiple* bounding boxes are generated, multiple index
reads are performed, and their results are all merged together.

Here's another example:

```sql
SELECT id,
  GEODIST(lat, lon, 55.777, 37.585, {in=deg,out=km}) d1,
  GEODIST(lat, lon, 55.569, 37.576, {in=deg,out=km}) d2,
  geodist(lat, lon, 56.860, 35.912, {in=deg,out=km}) d3,
  (d1<1 OR d2<1 OR d3<1) ok
FROM myindex WHERE ok=1
```

Note that if we reformulate the queries a little, and the optimizer does not
recognize the eligible cases any more, the optimization will *not* trigger. For
example:

```sql
SELECT *, 2*GEODIST(lat,lon,55.7540,37.6206,{in=deg,out=km})<=100 AS flag
FROM myindex WHERE flag=1
```

Obviously, "the bounding box optimization" is actually still feasible in this
case, but the optimizer will not recognize that and switch to full scan.

To ensure whether these optimizations are working for you, use `EXPLAIN` on your
query. Also, make sure the radius small enough when doing those checks.

Another interesting bit is that sometimes optimizer can quite *properly* choose
to only use one index instead of two, or avoid using the indexes at all.

Say, what if our radius covers the entire country? All our documents will be
within the bounding box anyway, and simple full scan will indeed be faster.
That's why you should use some "small enough" test radius with `EXPLAIN`.

Or say, what if we have another, super-selective `AND id=1234` condition in our
query? Doing index reads will be just as extraneous, the optimizer will choose
to perform a lookup by `id` instead.

### Multigeo support

[`MINGEODIST()`](#mingeodist-function),
[`MINGEODISTEX()`](#mingeodistex-function) and
[`CONTAINSANY()`](#containsany-function) functions let you have a *variable*
number of geopoints per row, stored as a simple JSON array of 2D coordinates.
You can then find either "close enough" rows with `MINGEODIST()`, additionally
identify the best geopoint in each such row with `MINGEODISTEX()`, or find rows
that have at least one geopoint in a given search polygon using `CONTAINSANY()`.
You can also speed up searches with a special `MULTIGEO` index.

The points must be stored as simple arrays of lat/lon values, in that order.
(For the record, we considered arrays of arrays as our "base" syntax too, but
rejected that idea.) We strongly recommend using degrees, even though there is
support for radians and one can still manage if one absolutely must. Here goes
an example with just a couple of points (think home and work addresses).

```sql
INSERT INTO test (id, j) VALUES
(123, '{"points": [39.6474, -77.463, 38.8974, -77.0374]}')
```

And you can then compute the distance to a given point to "the entire row", or
more formally, a minimum distance between some given point and all the points
stored in that row.

```sql
SELECT MINGEODIST(j.points, 38.889, -77.009, {in=deg}) md FROM test
```

If you also require the specific point index, not just the distance, then use
`MINGEODISTEX()` instead. It returns `<distance>, <index>` pair, but behaves as
`<distance>` in both `WHERE` and `ORDER BY` clauses. So the following returns
distances *and* geopoint indexes, sorted by distance.

```sql
SELECT MINGEODISTEX(j.points, 38.889, -77.009, {in=deg}) mdx FROM test
ORDER BY mdx DESC
```

Queries that limit `MINGEODIST()` to a certain radius can also be sped up using
attribute indexes too, just like "regular" `GEODIST()` queries!

For that, we must let Sphinx know in advance that our JSON field stores an array
of lat/lon pairs. That requires using the special `MULTIGEO()` "type" when
creating the attribute index on that field.

```sql
CREATE INDEX points ON test(MULTIGEO(j.points))
SELECT MINGEODIST(j.points, 38.889, -77.009, {in=deg, out=mi}) md
  FROM test WHERE md<10
```

With the `MULTIGEO` index in place, the `MINGEODIST()` and `MINGEODISTEX()`
queries can use bounding box optimizations discussed just above.


Searching: percolate queries
-----------------------------

Sphinx supports special percolate queries and indexes that let you perform
"reverse" searches and match documents against previously stored queries.

You create a special "percolate query index" (`type = pq`), you store queries
(literally contents of `WHERE` clauses) into that index, and you run special
percolate queries with `PQMATCH(DOCS(...))` syntax that match document contents
to previously stored queries. Here's a quick kick-off as to how.

```bash
index pqtest
{
    type = pq
    field = title
    attr_uint = gid
}
```

```sql
mysql> INSERT INTO pqtest VALUES
    -> (1, 'id > 5'),
    -> (2, 'MATCH(\'keyword\')'),
    -> (3, 'gid = 456');
Query OK, 3 rows affected (0.00 sec)

mysql> SELECT * FROM pqtest WHERE PQMATCH(DOCS(
    -> {111, 'this is doc1 with keyword', 123},
    -> {777, 'this is doc2', 234}));
+------+------------------+
| id   | query            |
+------+------------------+
|    2 | MATCH('keyword') |
|    1 | id > 5           |
+------+------------------+
2 rows in set (0.00 sec)
```

Now to the nitty gritty!

**The own, intrinsic schema of any PQ index is always just two columns.** First
column must be a `BIGINT` query id. Second column must be a query `STRING` that
stores a valid `WHERE` clause, such as those `id > 5` or `MATCH(...)` clauses
we used just above.

**In addition, PQ index must know its document schema.** We declare *that*
schema with `field` and `attr_xxx` config directives. And document schemas may
and do vary from one PQ index to another.

**In addition, PQ index must know its document text processing settings.**
Meaning that all the tokenizing, mapping, morphology, etc settings are all
perfectly supported, and will be used for `PQMATCH()` matching.

**Knowing all that, `PQMATCH()` matches stored queries to incoming documents.**
(Or to be precise, stored `WHERE` predicates, as they aren't complete queries.)

**Stored queries are essentially `WHERE` conditions.** Sans the `WHERE` itself.
Formally, you should be able to use any legal `WHERE` expression as your stored
query.

**Stored queries that match *ANY* of documents are returned.** In our example,
query 1 matches both tested documents (ids 111 and 777), query 2 only matches
one document (id 111), and query 3 matches none. Queries 1 and 2 get returned.

**Percolate queries work off temporary per-query RT indexes.** Every `PQMATCH()`
query does indeed create a tiny in-memory index with the documents it was given.
Then it basically runs all the previously stored searches against that index,
and drops it. So in theory you could get more or less the same results manually.

```sql
CREATE TABLE tmp (title FIELD, attr UINT);
INSERT INTO tmp VALUES
    (111, 'this is doc1 with keyword', 123),
    (777, 'this is doc2', 234);
SELECT 1 FROM tmp WHERE id > 5;
SELECT 2 FROM tmp WHERE MATCH('keyword');
SELECT 3 FROM tmp WHERE gid = 456;
DROP TABLE tmp;
```

Except that PQ indexes are optimized for that. First, PQ indexes avoid a bunch
of overheads that regular `CREATE`, `INSERT`, and `SELECT` statements incur.
Second, PQ indexes also analyze `MATCH()` conditions as you `INSERT` queries,
and very quickly reject documens that definitely *don't* match later when you
`PQMATCH()` the documents.

Still, **`PQMATCH()` works (much!) faster with batches of documents.** While
those overheads are reduced, they are not completely gone, and you can save on
that by batching. Running 100 percolate queries with just 1 document can easily
get 10 to 20 *times* slower than running just 1 equivalent percolate query with
all 100 documents in it. So if you can batch, do batch.

**PQ queries can return the matched docids too, via PQMATCHED().** This special
function only works with `PQMATCH()` queries. It returns a comma-separated list
of documents IDs from `DOCS(...)` that did match the "current" stored query, for
instance:

```sql
mysql> SELECT id, PQMATCHED(), query FROM pqtest
    -> WHERE PQMATCH(DOCS({123, 'keyword'}, {234, 'another'}));
+------+-------------+--------+
| id   | PQMATCHED() | query  |
+------+-------------+--------+
|    3 | 123,234     | id > 0 |
+------+-------------+--------+
1 row in set (0.00 sec)
```

**`DOCS()` rows must have all columns, and in proper "insert schema" order.**
Meaning, documents in `DOCS()` must have all their columns (including ID), and
the columns must be in the exact PQ index config order.

Sounds kinda scary, but in reality you simply pass exactly the same data in
`DOCS()` as you would in `INSERT`document, and that's it. On any mismatch,
`PQMATCH()` just fails, with a hopefully helpful error message.

**`DOCS()` is currently limited to at most 10000 documents.** So checking 50K
documents must be split into 5 different `PQMATCH()` queries.

**To manage data stored in PQ indexes, use basic CRUD queries.** The supported
ones are very basic and limited just yet, but they get the job done.

  - `INSERT` and `REPLACE` both work;
  - `SELECT ... LIMIT ...` works;
  - `DELETE ... WHERE id ...` works.

For instance!

```sql
mysql> select * from pqtest;
+------+------------------+
| id   | query            |
+------+------------------+
|    1 | id > 5           |
|    2 | MATCH('keyword') |
|    3 | gid = 456        |
+------+------------------+
3 rows in set (0.00 sec)
```

**PQ indexes come with a built-in size sanity check.** There's a maximum row
count (aka maximum stored queries count), controlled by `pq_max_rows` directive.
It defaults to 1,000,000 queries. (Because a million queries must be enough for
eve.. er, for one core.)

Once you hit it, you can't insert more stored queries until you either remove
some, or adjust the limit. That can be done online easily.

```sql
ALTER TABLE pqtest SET OPTION pq_max_rows=2000000;
```

Why even bother? Stored queries take very little RAM, but they may burn quite
a lot of CPU. Remember that *every* `PQMATCH()` query needs to test its incoming
`DOCS()` against *all* the stored queries. There should be *some* safety net,
and `pq_max_rows` is it.

**PQ indexes are binlogged.** So basically the data you `INSERT` is crash-safe.

**PQ indexes are *not* regular FT indexes, and they are additionally limited.**
In a number of ways. Many familiar operations won't work (some yet, some ever).
Here are a few tips.

  - `SELECT` does not support any `WHERE` or `ORDER` etc clauses yet;
  - `INSERT` does not support column list, it's always `(id, 'query')` pairs;
  - `DELETE` only supports explicit `WHERE id=...` and `WHERE id IN (...)`;
  - `DESCRIBE` does not work yet;
  - PQ indexes can not be used in distributed indexes;
  - no automatic periodic flushes yet (manual `FLUSH INDEX` works though).


Searching: vector searches
---------------------------

You can implement vector searches with Sphinx and there are several different
features intended for that, namely:

  * fixed array attributes, eg. `attr_int8_array = vec1[128]`
  * JSON array attributes, eg. `{"vec2": int8[1,2,3,4]}`
  * [`DOT()` function](#dot-function) to compute dot products
  * [`FVEC()` function](#fvec-function) to specify vector constants

Let's see how all these parts connect together.

**First, storage.** You can store your per-document vectors using any of the
following options:

  * fixed-size fixed-type arrays, ie. `attr_XXX_array` directive
  * JSON arrays with implicit types, ie. regular `[1,2,3,4]` values in JSON
  * JSON arrays with explicit types, ie. `int8[1,2,3,4]` or `float[1,2,3,4]`
    syntax extensions

Fixed arrays are the fastest to access, but not flexible at all. Also, they
require some RAM per every document. For instance, a fixed array with 32 floats
(`attr_float_array = test1[32]`) will consume 128 bytes per *every* row, whether
or not it contains any actual data (and arrays without any explicit data will be
filled with zeroes).

JSON arrays are slower to access, and consume a bit more memory per row, but
that memory is only consumed per *used* row. Meaning that when your vectors are
defined sparsely (for, say, just 1M documents out of the entire 10M collection),
then it might make sense to use JSON anyway to save some RAM.

JSON arrays are also "mixed" by default, that is, can contain values with
arbitrary different types. With vector searches however you would normally want
to use optimized arrays, with a single type attached to *all* values. Sphinx can
auto-detect integer arrays in JSON, with values that fit into either int32 or
int64 range, and store and later process them efficiently. However, to enforce
either int8 or float type on a JSON array, you have to *explicitly* use our
JSON syntax extensions.

To store an array of `float` values in JSON, you have to:

  * either specify `float` type in each value with `1.234f` syntax (because by
    default `1.234` gets a `double` type in JSON), eg: `[1.0f, 2.0f, 3.0f]`
  * or specify array type with `float[...]` syntax, eg: `float[1,2,3]`

To store an array of `int8` values (ie. from -128 to 127 inclusive) in JSON,
the only option is to:

  * specify array type with `int8[...]` syntax, eg: `int8[1,2,3]`

In both these cases, we require an explicit type to differentiate between
the two possible options (`float` vs `double`, or `int8` vs `int` case), and
by default, we choose to use higher precision rather than save space.

**Second, calculations.** The workhorse here is the `DOT()` function that
computes a dot product between the two vector arguments, ie. a sum of the
products of the corresponding vector components.

The most frequent usecase is, of course, computing a `DOT()` between some
per-document array (stored either as an attribute or in JSON) and a constant.
The latter should be specified with `FVEC()`:

```sql
SELECT id, DOT(vec1, FVEC(1,2,3,4)) FROM mydocuments
SELECT id, DOT(json.vec2, FVEC(1,2,3,4)) FROM mydocuments
```

Note that `DOT()` internally optimizes its execution depending on the actual
argument types (ie. float vectors, or integer vectors, etc). That is why the
two following queries perform very differently:

```sql
mysql> SELECT id, DOT(vec1, FVEC(1,2,3,4,...)) d
  FROM mydocuments ORDER BY d DESC LIMIT 3;
...
3 rows in set (0.047 sec)

mysql> SELECT id, DOT(vec1, FVEC(1.0,2,3,4,...)) d
  FROM mydocuments ORDER BY d DESC LIMIT 3;
...
3 rows in set (0.073 sec)
```

In this example, `vec1` is an integer array, and we `DOT()` it against either
an integer constant vector, or a float constant vector. Obviously, int-by-int
vs int-by-float multiplications are a bit different, and hence the performance
difference.


Searching: vector indexes
--------------------------

> NOTE: as of v.3.6 vector index support is still not yet available in public
builds because of certain dependency complications (aka DLL hell).

In addition to brute-force vector searches described just above, Sphinx also
supports fast approximate searches with "vector indexes", or more formally, ANN
indexes (Approximate Nearest Neighbour indexes). They accelerate top-K searches
by dot product with a constant reference vector. Let's jumpstart.

The simplest way to check out vector indexes in action is as follows.

  1. Create an attribute index on an array column with your vector.
  2. Have or insert "enough" rows into your FT index.
  3. Run `SELECT` queries with the `ORDER BY DOT()` condition on that vector.

For example, assuming that the we have an FT index called `rt` with a 4D float
array column `vec` declared with `attr_float_array = vec[4]`, and assuming that
we have enough data in that index (say, 1M rows):

```sql
-- slower exact query, scans all rows
SELECT id, DOT(vec, FVEC(1,2,3,4)) d FROM rt ORDER BY d ASC

-- create the vector index (may take a while)
CREATE INDEX vec ON rt(vec);

-- faster ANN query now
SELECT id, DOT(vec, FVEC(1,2,3,4)) d FROM rt ORDER BY d ASC

-- slower exact query is still possible too
SELECT id, DOT(vec, FVEC(1,2,3,4)) d FROM rt IGNORE INDEX(vec) ORDER BY d ASC
```

That's pretty much it, actually. Even for production.

We intentionally do not have many tweaking knobs here. Instead, we spent some
time making everything as automatic as we can. However, gotta elaborate on that
recurring "have enough rows" theme.

**Vector indexes only engage on a rather large collection; intentionally so.**
Both vector index maintenance *and* queries come with their overheads, and we
found that for not-so-large segments (up to about 200K documents) it's quicker
on average to honestly compute DOT(), especially with our SIMD-optimized
implementations.

**There's a tweakable size threshold that you might not really wanna tweak.**
The config setting is [`vecindex_thresh`](#vecindex_thresh-directive), it is
server-wide, and its current default value is 170000 (170K documents), derived
from our tests on various mixed workloads (so hopefully "generic enough").

Of course, as your workloads might differ, your own optimal threshold might
differ. However, if you decide to go that route and optimize tweak that, beware
that our defaults *may* change in future releases. Simply to optimize better for
any future internal changes. You would have to retest then. You also wouldn't
want to ignore the changelogs.

**Vector indexes only engage for top-K-dot queries.** Naturally, also known as
the "nearest neighbour" queries. That's the only type of query (a significant
one though!) they can help with.

**Vector indexes may and *will* produce approximate results!** Naturally again,
they are approximate, meaning that for the sake of the speed they may and *will*
loose one of the very best matches in your top-K set.

**Vector indexes do not universally help; and you should rely on the planner.**
Assume that a very selective `WHERE` condition only matches a few rows; say,
literally 10 rows. Directly computing just 10 dot products and ordering by those
is (much) cheaper than even *initializing* a vector query. Query planer takes
that into account, and tries to pick the better execution path, either with or
without the vector indexes.

**You can force the vector indexes on and off using the FORCE/IGNORE syntax.**
Just as with the regular ones. This is useful either when planner fails, or just
for performance testing.

**Vector index construction can be accelerated too, by pretraining.** For that,
you first need to run `indexer pretrain` on a training dataset (for larger
production datasets, a random subset works great), and then use the pretraining
result with the [`pretrained_index`](#pretrained_index-directive) directive.

**Vector queries only utilize a single core per local index.** Intentionally.
While using many available CPU cores for a single search is viable, and does
improve one-off latencies, that only works well with exactly 1 client. And with
*multiple* concurrent clients and mixed workloads (that mix vector and regular
queries) we find that to be a complete and utter operational nightmare, as in,
overbooking cores by a factor of 10 one second, then underusing then by a factor
of 10 the very next second. Hence, no. Just no.

**All the array values types are supported.** Vector indexes can be built either
over wider `FLOAT_ARRAY` columns or leaner `INT8_ARRAY` ones, no restrictions.

**Vectors stored in JSON are intentionally not supported.** That's both slower
and harder to properly maintain (again on the ops side, not really Sphinx side).
Basically, because the data in JSON is just not typed strongly enough. Vector
indexes always have a fixed number of dimensions anyway, and arrays guarantee
that easily, while storing that kind of data in JSON is quite error prone (and
slower to access too).


Searching: memory budgets
--------------------------

Result sets in Sphinx never are arbitrarily big. There always is a `LIMIT`
clause, either an explicit or an implicit one.

Result set sorting and grouping therefore never consumes an arbitrarily large
amount of RAM. Or in other words, sorters always run on a memory budget.

Previously, the actual "byte value" for that budget depended on few things,
including the pretty quirky `max_matches` setting. It was rather complicated to
figure out that "byte value" too.

Starting with v.3.5, **we are now counting that budget merely in bytes**, and
**the default budget is 50 MB per each sorter**. (Which is *much* higher than
the previous default value of just 1000 matches per sorter.) You can override
this budget on a per query basis using the `sort_mem` query option, too.

```sql
SELECT gid, count(*) FROM test GROUP BY gid OPTION sort_mem=100000000
```

Size suffixes (`k`, `m`, and `g`, case-insensitive) are supported. The maximum
value is `2G`, ie. 2 GB per sorter.

```sql
SELECT * FROM test OPTION sort_mem=1024; /* this is bytes */
SELECT * FROM test OPTION sort_mem=128k;
SELECT * FROM test OPTION sort_mem=256M;
```

"Per sorter" budget applies to each facet. For example, the default budget means
either 50 MB per query for queries without facets, or 50 MB per each facet for
queries *with* facets, eg. up to 200 MB for a query with 4 facets (as in, 1 main
leading query, and 3 `FACET` clauses).

**Hitting that budget WILL affect your search results!**

There are two different cases here, namely, queries with and without `GROUP BY`
(or `FACET`) clauses.

**Case 1, simple queries without any GROUP BY.** For non-grouping queries you
can only manage to hit the budget by setting the `LIMIT` high enough.

```sql
/* requesting 1 billion matches here.. probably too much eh */
SELECT * FROM myindex LIMIT 1000000000
```

In this example `SELECT` simply warns about exceeding the memory budget, and
returns fewer matches than requested. Even if the index has enough. Sorry, not
enough memory to hold and sort *all* those matches. The returned matches are
still in the proper order, everything but the `LIMIT` must also be fine, and
`LIMIT` is effectively auto-adjusted to fit into `sort_mem` budget. All very
natural.

**Case 2, queries with GROUP BY.** For grouping queries, ie. those with either
`GROUP BY` and/or `FACET` clauses (that also perform grouping!) the `SELECT`
behavior gets a little more counter-intuitive.

Grouping queries must ideally keep *all* the "interesting" groups in RAM at all
times, whatever the `LIMIT` value. So that they could *precisely* compute the
final aggregate values (counts, averages, etc) in the end.

But if there are extremely many groups, just way too many to keep within the
allowed `sort_mem` budget, the sorter *has* to throw something away, right?!
And sometimes that may even happen to the "best" row or the entire "best" group!
Just because at the earlier point in time when the sorter threw it away it
didn't yet know that it'd be our best result in the end.

Here's an actual example with a super-tiny budget that only fits 2 groups, and
where the "best", most frequent group gets completely thrown out.

```sql
mysql> select *, count(*) cnt from rt group by x order by cnt desc;
+----+----+-----+
| id | x  | cnt |
+----+----+-----+
|  3 | 30 |   3 |
|  1 | 10 |   2 |
|  2 | 20 |   2 |
+----+----+-----+
3 rows in set (0.00 sec)

mysql> select *, count(*) cnt from rt group by x order by cnt desc option sort_mem=200;
+----+----+-----+
| id | x  | cnt |
+----+----+-----+
|  1 | 10 |   2 |
|  2 | 20 |   2 |
+----+----+-----+
2 rows in set (0.00 sec)

mysql> show warnings;
+---------+------+-----------------------------------------------------------------------------------+
| Level   | Code | Message                                                                           |
+---------+------+-----------------------------------------------------------------------------------+
| warning | 1000 | sorter out of memory budget; rows might be missing; aggregates might be imprecise |
+---------+------+-----------------------------------------------------------------------------------+
1 row in set (0.00 sec)
```

Of course, to alleviate the issue a little there's a warning that `SELECT` ran
out of memory, had to throw out some data, and that the result set *may* be off.
Unfortunately, it's impossible to tell how much off it is. There's no memory to
tell that!

Bottom line, if you ever need huge result sets with lots of groups, you might
either need to extend `sort_mem` respectively to make your results precise, or
have to compromise between query speed and resulting accuracy. If (and only if!)
the `sort_mem` budget limit is reached, then the smaller the limit is, the
faster the query will execute, but with lower accuracy.

**How many is "too many" in rows (or groups), not bytes?** What if after all we
occasionally need to approximately map the `sort_mem` limit from bytes to rows?

For the record, internally Sphinx *estimates* the sorter memory usage rather
than rigorously tracking every byte. **That makes `sort_mem` a soft limit**, and
actual RAM usage might be just a bit off. That also makes it still possible, if
a whiff complicated, to estimate the limits in matches (rows or groups) rather
than bytes.

Sorters must naturally keep all computed expressions for every row. Note how
those include internal counters for grouping itself and computing aggregates:
that is, the grouping key, row counts, etc. In addition, any sorter needs a few
extra overhead bytes per each row for "bookkeeping": as of v.3.5, 32 bytes for
a sorter without grouping, 44 bytes for a sorter with `GROUP BY`, and 52 bytes
for a `GROUP <N> BY` sorter.

So, for example, `SELECT id, title, id+1 q, COUNT(*) FROM test GROUP BY id` would
use the memory as follows:

  - 20 bytes per row for expressions
    - 8 bytes for `id+1`
    - 8 bytes for `GROUP BY` key
    - 4 bytes for `COUNT(*)`
  - 44 bytes per row for sorter
  - 64 bytes per row total

For a default 50 MB limit that gives us up to 819200 groups. If we have more
groups than that, we either must bump `sort_mem`, or accept the risk that the
query result won't be exact.

Last but not least, **sorting memory budget does NOT apply to result sets!**
Assume that the average `title` length just above is 100 bytes, each result set
group takes a bit over 120 bytes, and with 819200 groups we get a beefy 98.3 MB
result set.

And that result set gets returned in full, without any truncation. Even with
the default 50 MB budget. Because the `sort_mem` limit only affects sorting and
grouping internals, not the final result sets.


Searching: distributed query errors
------------------------------------

**Distributed query errors are now intentionally strict starting from v.3.6.**
In other words, **queries must now fail if any single agent (or local) fails.**

Previously, the default behavior has very long been was to convert individual
component (agent or local index) errors into warnings. Sphinx kinda tried hard
to return at least partially "salvaged" result set built from whatever it could
get from the non-erroneous components.

These days we find that behavior misleading and hard to operate. Monitoring,
retries, and debugging all become too complicated. We now consider "partial"
errors hard errors by default.

**You can still easily enable the old behavior** (to help migrating from older
Sphinx versions) by using `OPTION lax_agent_errors=1` in your queries. Note that
we strongly suggest only using that option temporarily, though. Most all queries
must *NOT* default to the lax mode.

For example, consider a case where we have 2 index shards in our distributed
index, both local. Assume that we have just run a successful online `ALTER` on
the first shard, adding a new "tag" column, but not on the second one just yet.
This is a valid scenario so far, and queries in general would work okay. Because
the distributed index components are quite allowed to have differing schemas.

```sql
mysql> SELECT * FROM shard1;
+------+-----+------+
| id   | uid | tag  |
+------+-----+------+
|   41 |   1 |  404 |
|   42 |   1 |  404 |
|   43 |   1 |  404 |
+------+-----+------+
3 rows in set (0.00 sec)

mysql> SELECT * FROM shard2;
+------+-----+
| id   | uid |
+------+-----+
|   51 |   2 |
|   52 |   2 |
|   53 |   2 |
+------+-----+
3 rows in set (0.00 sec)

mysql> SELECT * FROM dist;
+------+-----+
| id   | uid |
+------+-----+
|   41 |   1 |
|   42 |   1 |
|   43 |   1 |
|   51 |   2 |
|   52 |   2 |
|   53 |   2 |
+------+-----+
3 rows in set (0.00 sec)
```

However, if we start using the newly added `tag` column with the `dist` index
that's exactly the kind of an issue that is now a hard error. Too soon, because
the column was not yet added everywhere.

```sql
mysql> SELECT id, tag FROM dist;
ERROR 1064 (42000): index 'shard2': parse error: unknown column: tag
```

We used local indexes in our example, but this works (well, fails!) in exactly
the same way when using the remote agents. The specific error message may differ
but the error *must* happen.

Previously you would get a partial result set with a warning instead. That can
still be done but now that requires an explicit option.

```sql
mysql> SELECT id, tag FROM dist OPTION lax_agent_errors=1;
+------+------+
| id   | tag  |
+------+------+
|   41 |  404 |
|   42 |  404 |
|   43 |  404 |
+------+------+
3 rows in set, 1 warning (0.00 sec)

mysql> SHOW META;
+---------------+--------------------------------------------------+
| Variable_name | Value                                            |
+---------------+--------------------------------------------------+
| warning       | index 'shard2': parse error: unknown column: tag |
| total         | 3                                                |
| total_found   | 3                                                |
| time          | 0.000                                            |
+---------------+--------------------------------------------------+
4 rows in set (0.00 sec)
```

Beware that these errors may become unavoidably srtict, and this workaround-ish
option just *MAY* get deprecated and then removed at some future point. So if
your index setup somehow really absolutely unavoidably requires "intentionally
semi-erroneous" queries like that, you should rewrite them using other SphinxQL
features that, well, let you avoid errors.

To keep our example going, even if for some reason we absolutely must utilize
the new column ASAP (and could not even wait for the second `ALTER` to finish),
we can use the `EXIST()` pseudo-function:

```sql
mysql> SELECT id, EXIST('tag', 0) xtag FROM dist;
+------+------+
| id   | xtag |
+------+------+
|   41 |  404 |
|   42 |  404 |
|   43 |  404 |
|   51 |    0 |
|   52 |    0 |
|   53 |    0 |
+------+------+
6 rows in set (0.00 sec)
```

That's no errors, no warnings, and more data. Usually considered a good thing.

A few more quick notes about this change, in no particular order:

  - `FACET` queries are *not* affected, only the distributed indexes are. Facet
    queries remain "independent" in the sense that an error in an individual
    facet does not affect any other facets;
  - both local and remote indexes are affected, as they are considered
    completely equal from the perspective of the encompassing distributed index;
  - so despite the name, `lax_agent_errors` also applies very well to the local
    components of  distributed index (`relaxed_agent_or_local_errors` would be
    more precise but way too long);
  - "implicit" distributed indexes are also affected, meaning that queries like
    `SELECT ... FROM shard1, shard2` are now more strict too.


Ranking: factors
-----------------

Sphinx lets you specify custom ranking formulas for `weight()` calculations, and
tailor text-based relevance ranking for your needs. For instance:

```sql
SELECT *, WEIGHT() FROM myindex WHERE MATCH('hello world')
OPTION ranker=expr('sum(lcs)*10000+bm15')
```

This mechanism is called the **expression ranker** and its ranking formulas
(expressions) can access a few more special variables, called ranking factors,
than a regular expression. (Of course, all the per-document attributes and all
the math and other functions are still accessible to these formulas, too.)

**Ranking factors (aka ranking signals)** are, basically, a bunch of different
values computed for every document (or even field), based on the current search
query. They essentially describe various aspects of the specific document match,
and so they are used as input variables in a ranking formula, or a ML model.

There are three types (or levels) of factors, that determine when exactly some
given factor can and will be computed:

  * **query factors**: values that only depend on the search query, but not the
    document, like `query_word_count`;
  * **document factors**: values that depend on both the query *and* the matched
    document, like `doc_word_count` or `bm15`;
  * **field factors**: values that depend on both the query *and* the matched
    full-text field, like `word_count` or `lcs`.

**Query factors** are naturally computed just once at the query start, and from
there they stay constant. Those are usually simple things, like a number of
unique keywords in the query. You can use them anywhere in the ranking formula.

**Document factors** additionally depend on the document text, and so they get
computed for every matched document. You can use them anywhere in the ranking
formula, too. Of these, a few variants of the classic `bm25()` function are
arguably the most important for relevance ranking.

Finally, **field factors** are even more granular, they get computed for every
single field. And thus they then have to be aggregated into a singular value by
some **factor aggregation function** (as of v.3.2, the supported functions are
either `SUM()` or `TOP()`).

**Factors can be optional, aka null.** For instance, by default no fields are
implicitly indexed for trigrams, and all the trigram factors are undefined, and
they get null values. Those null values are suppressed from `FACTORS()` JSON
output. However, internally they are implemented using some magic values of the
original factor type rather than some "true" nulls of a special type. So in both
UDFs and ranking expressions you will get those magic values, and you may have
to interpret them as nulls.

Keeping the trigrams example going, trigram factors are nullified when `trf_qt`
(which has a `float` type) is set to -1, while non-null values of `trf_qt` must
always be in 0..1 range. All the other `trf_xxx` signals get zeroed out. Thus,
to properly differentiate between null and zero values of some *other* factor,
let's pick `trf_i2u` for example, you will have to check not even the `trf_i2u`
value itself (because it's zero in both zero and null cases), but you have to
check `trf_qt` value for being less than zero. Ranking is fun.

And before we discuss every specific factor in a bit more detail, here goes the
obligatory **factors cheat sheet**. Note that:

  - **Hits** in Sphinx == postings in IR == formally "a number of (a certain
    type of) matching keyword occurrences in the current field"
  - **"Opt"** column says "yes" when the factor (ie. signal) is optional

| Name                 | Level | Type  | Opt | Summary                                                                                   |
|----------------------|-------|-------|-----|-------------------------------------------------------------------------------------------|
| has_digit_words      | query | int   |     | number of `has_digit` words that contain `[0-9]` chars (but may also contain other chars) |
| is_latin_words       | query | int   |     | number of `is_latin` words, ie. words with `[a-zA-Z]` chars only                          |
| is_noun_words        | query | int   |     | number of `is_noun` words, ie. tagged as nouns (by the lemmatizer)                        |
| is_number_words      | query | int   |     | number of `is_number` words, ie. integers with `[0-9]` chars only                         |
| max_lcs              | query | int   |     | maximum possible LCS value for the current query                                          |
| query_tokclass_mask  | query | int   | yes | mask of token classes (if any) found in the current query                                 |
| query_word_count     | query | int   |     | number of unique inclusive keywords in a query                                            |
| words_clickstat      | query | float | yes | `sum(clicks)/sum(events)` over matching words with "clickstats" in the query              |
| annot_exact_hit      | doc   | int   | yes | whether any annotations entry == annot-field query                                        |
| annot_exact_order    | doc   | int   | yes | whether all the annot-field keywords were a) matched and b) in query order, in any entry  |
| annot_hit_count      | doc   | int   | yes | number of individual annotations matched by annot-field query                             |
| annot_max_score      | doc   | float | yes | maximum score over matched annotations, additionally clamped by 0                         |
| annot_sum_idf        | doc   | float | yes | sum_idf for annotations field                                                             |
| bm15                 | doc   | float |     | quick estimate of `BM25(1.2, 0)` without query syntax support                             |
| bm25a(k1, b)         | doc   | int   |     | precise `BM25()` value with configurable `K1`, `B` constants and syntax support           |
| bm25f(k1, b, ...)    | doc   | int   |     | precise `BM25F()` value with extra configurable field weights                             |
| doc_word_count       | doc   | int   |     | number of unique keywords matched in the document                                         |
| field_mask           | doc   | int   |     | bit mask of the matched fields                                                            |
| atc                  | field | float |     | Aggregate Term Closeness, `log(1+sum(idf1*idf2*pow(dist, -1.75))` over "best" term pairs  |
| exact_field_hit      | field | bool  |     | whether field is fully covered by the query, in the query term order                      |
| exact_hit            | field | bool  |     | whether query == field                                                                    |
| exact_order          | field | bool  |     | whether all query keywords were a) matched and b) in query order                          |
| full_field_hit       | field | bool  |     | whether field is fully covered by the query, in arbitrary term order                      |
| has_digit_hits       | field | int   |     | number of `has_digit` keyword hits                                                        |
| hit_count            | field | int   |     | total number of any-keyword hits                                                          |
| is_latin_hits        | field | int   |     | number of `is_latin` keyword hits                                                         |
| is_noun_hits         | field | int   |     | number of `is_noun` keyword hits                                                          |
| is_number_hits       | field | int   |     | number of `is_number` keyword hits                                                        |
| lccs                 | field | int   |     | Longest Common Contiguous Subsequence between query and document, in words                |
| lcs                  | field | int   |     | Longest Common Subsequence between query and document, in words                           |
| max_idf              | field | float |     | `max(idf)` over keywords matched in this field                                            |
| max_window_hits(n)   | field | int   |     | `max(window_hit_count)` computed over all N-word windows in the current field             |
| min_best_span_pos    | field | int   |     | first maximum LCS span position, in words, 1-based                                        |
| min_gaps             | field | int   |     | min number of gaps between the matched keywords over the matching spans                   |
| min_hit_pos          | field | int   |     | first matched occurrence position, in words, 1-based                                      |
| min_idf              | field | float |     | `min(idf)` over keywords matched in this field                                            |
| phrase_decay10       | field | float |     | field to query phrase "similarity" with 2x weight decay per 10 positions                  |
| phrase_decay30       | field | float |     | field to query phrase "similarity" with 2x weight decay per 30 positions                  |
| sum_idf              | field | float |     | `sum(idf)` over unique keywords matched in this field                                     |
| sum_idf_boost        | field | float |     | `sum(idf_boost)` over unique keywords matched in this field                               |
| tf_idf               | field | float |     | `sum(tf*idf)` over unique matched keywords, ie. `sum(idf)` over all occurrences           |
| trf_aqt              | field | float | yes | Trigram Filter Alphanumeric Query Trigrams ratio                                          |
| trf_i2f              | field | float | yes | Trigram Filter Intersection To Field ratio                                                |
| trf_i2q              | field | float | yes | Trigram Filter Intersection to Query ratio                                                |
| trf_i2u              | field | float | yes | Trigram Filter Intersection to Union ratio                                                |
| trf_naqt             | field | float | yes | Trigram Filter Number of Alphanumeric Query Trigrams                                      |
| trf_qt               | field | float | yes | Trigram Filter Query Trigrams ratio                                                       |
| user_weight          | field | int   |     | user-specified field weight (via `OPTION field_weights`)                                  |
| wlccs                | field | float |     | Weighted LCCS, `sum(idf)` over contiguous keyword spans                                   |
| word_count           | field | int   |     | number of unique keywords matched in this field                                           |
| wordpair_ctr         | field | float |     | `sum(clicks) / sum(views)` over all the matching query-vs-field raw token pairs           |

### Accessing ranking factors

You can access the ranking factors in several different ways. Most of them
involve using the special `FACTORS()` function.

  1. `SELECT FACTORS()` formats all the (non-null) factors as a JSON document.
     **This is the intended method for ML export tasks**, but also useful for
     debugging.
  2. `SELECT MYUDF(FACTORS())` passes all the factors (*including* null ones) to
     your UDF function. **This is the intended method for ML inference tasks**,
     but it could of course be used for something else, for instance, exporting
     data in a special format.
  3. `SELECT FACTORS().xxx.yyy` returns an individual signal as a scalar value
     (either `UINT` or `FLOAT` type). **This is mostly intended for debugging.**
     However, note some of the factors are not yet supported as of v.3.5.
  4. For the record, `SELECT WEIGHT() ... OPTION ranker=expr('...')` returns the
     ranker formula evaluation result in the `WEIGHT()` and a carefully crafted
     formula could also extract individual factors. That's a legacy debugging
     workaround though. Also, as of v.3.5 some of the factors might not be
     accessible to formulas, too. (By oversight rather than by design.)

Bottom line, `FACTORS()` and `MYUDF(FACTORS())` are our primary workhorses, and
those have full access to everything.

But `FACTORS()` output gets rather big these days, so it's frequently useful to
pick out individual signals, and `FACTORS().xxx.yyy` syntax does just that.

As of v.3.5 it lets you access most of the field-level signals, either by field
index or field name. Missing fields or null values will be fixed up to zeroes.

```
SELECT id, FACTORS().fields[3].atc ...
SELECT id, FACTORS().fields.title.lccs ...
```

### Factor aggregation functions

Formally, a (field) factor aggregation function is a single argument function
that takes an expression with field-level factors, iterates it over all the
matched fields, and computes the final result over the individual per-field
values.

Currently supported aggregation functions are:

  * `SUM()`, sums the argument expression over all matched fields. For instance,
    `sum(1)` should return a number of matched fields.
  * `TOP()`, returns the greatest value of the argument over all matched fields.
     For instance, `top(max_idf)` should return a maximum per-keyword IDF over
     the entire document.

Naturally, these are only needed over expressions with field-level factors,
query-level and document-level factors can be used in the formulas "as is".

### Keyword flags

When searching and ranking, Sphinx classifies every query keyword with regards
to a few classes of interest. That is, it flags a keyword with a "noun" class
when the keyword is a (known) noun, or flags it with a "number" class when it is
an integer, etc.

At the moment we identify 4 keyword classes and assign the respective flags.
Those 4 flags in turn generate 8 ranking factors, 4 query-level per-flag keyword
counts, and 4 field-level per-class hit counts. The flags are described in a bit
more detail just below.

It's important to understand that all the flags are essentially assigned at
*query* parsing time, without looking into any actual index *data* (as opposed
to tokenization and morphology settings). Also, query processing rules apply.
Meaning that the valid keyword modifiers are effectively stripped before
assigning the flags.

#### `has_digit` flag

Keyword is flagged as `has_digit` when there is at least one digit character,
ie. from `[0-9]` range, in that keyword.

Other characters are allowed, meaning that `l33t` is a `has_digit` keyword.

But they are not required, and thus, any `is_number` keyword is by definition
a `has_digit` keyword.

#### `is_latin` flag

Keyword is flagged as `is_latin` when it completely consists of Latin letters,
ie. any of the `[a-zA-Z]` characters. No other characters are allowed.

For instance, `hello` is flagged as `is_latin`, but `l33t` is *not*, because
of the digits.

Also note that wildcards like `abc*` are *not* flagged as `is_latin`, even if
all the actual expansions are latin-only. Technically, query keyword flagging
only looks at the query itself, and not the index data, and can not know
anything about the actual expansions yet. (And even if it did, then inserting
a new row with a new expansion could suddenly break the `is_latin` property.)

At the same time, as query keyword modifiers like `^abc` or `=abc` still get
properly processed, these keywords *are* flagged as `is_latin` alright.

#### `is_noun` flag

Keyword is flagged as `is_noun` when (a) there is at least one lemmatizer
enabled for the index, and (b) that lemmatizer classifies that standalone
keyword as a noun.

For example, with `morphology = lemmatize_en` configured in our example index,
we get the following:

```
mysql> CALL KEYWORDS('deadly mortal sin', 'en', 1 AS stats);
+------+-----------+------------+------+------+-----------+------------+----------------+----------+---------+-----------+-----------+
| qpos | tokenized | normalized | docs | hits | plain_idf | global_idf | has_global_idf | is_latin | is_noun | is_number | has_digit |
+------+-----------+------------+------+------+-----------+------------+----------------+----------+---------+-----------+-----------+
| 1    | deadly    | deadly     | 0    | 0    | 0.000000  | 0.000000   | 0              | 1        | 0       | 0         | 0         |
| 2    | mortal    | mortal     | 0    | 0    | 0.000000  | 0.000000   | 0              | 1        | 1       | 0         | 0         |
| 3    | sin       | sin        | 0    | 0    | 0.000000  | 0.000000   | 0              | 1        | 1       | 0         | 0         |
+------+-----------+------------+------+------+-----------+------------+----------------+----------+---------+-----------+-----------+
3 rows in set (0.00 sec)
```

However, as you can see from this very example, `is_noun` POS tagging is not
completely precise.

For now it works on individual words rather than contexts. So even though in
*this* particular query context we could technically guess that "mortal" is not
a noun, in general it sometimes is. Hence the `is_noun` flags in this example
are 0/1/1, though ideally they would be 0/0/1 respectively.

Also, at the moment the tagger prefers to overtag. That is, when "in doubt",
ie. when the lemmatizer reports that a given wordform can either be a noun or
not, we do not (yet) analyze the probabilities, and just always set the flag.

Another tricky bit is the handling of non-dictionary forms. As of v.3.2 the
lemmatizer reports all such predictions as nouns.

So use with care; this can be a noisy signal.

#### `is_number` flag

Keyword is flagged as `is_number` when *all* its characters are digits from
the `[0-9]` range. Other characters are not allowed.

So, for example, `123` will be flagged `is_number`, but neither `0.123` nor
`0x123` will be flagged.

To nitpick on this particular example a bit more, note that `.` does not even
get parsed as a character by default. So with the default `charset_table` that
query text will not even produce a single keyword. Instead, by default it gets
tokenized as two tokens (keywords), `0` and `123`, and *those* tokens in turn
*are* flagged `is_number`.


### Query-level ranking factors

These are perhaps the simplest factors. They are entirely independent from the
documents being ranked; they only describe the query. So they only get computed
once, at the very start of query processing.

#### has_digit_words

Query-level, a number of unique `has_digit` keywords in the query. Duplicates
should only be accounted once.

#### is_latin_words

Query-level, a number of unique `is_latin` keywords in the query. Duplicates
should only be accounted once.

#### is_noun_words

Query-level, a number of unique `is_noun` keywords in the query. Duplicates
should only be accounted once.

#### is_number_words

Query-level, a number of unique `is_number` keywords in the query. Duplicates
should only be accounted once.

#### max_lcs

Query-level, maximum possible value that the `sum(lcs*user_weight)` expression
can take. This can be useful for weight boost scaling. For instance, (legacy)
`MATCHANY` ranker formula uses this factor to *guarantee* that a full phrase
match in *any* individual field ranks higher than any combination of partial
matches in all fields.

#### query_word_count

Query-level, a number of unique and inclusive keywords in a query. "Inclusive"
means that it's additionally adjusted for a number of excluded keywords. For
example, both `one one one one` and `(one !two)` queries should assign a value
of 1 to this factor, because there is just one unique non-excluded keyword.


### Document-level ranking factors

These are a few factors that "look" at both the query and the (entire) matching
document being ranked. The most useful among these are several variants of the
classic BM-family factors (as in Okapi BM25).

#### bm15

Document-level, a quick estimate of a classic `BM15(1.2)` value. It is computed
without keyword occurrence filtering (ie. over all the term postings rather than
just the matched ones). Also, it ignores the document and fields lengths.

For example, if you search for an exact phrase like `"foo bar"`, and both `foo`
and `bar` keywords occur 10 times each in the document, but the *phrase* only
occurs once, then this `bm15` estimate will still use 10 as TF (Term Frequency)
values for both these keywords, ie. account all the term occurrences (postings),
instead of "accounting" just 1 actual matching posting.

So `bm15` uses pre-computed document TFs, rather that computing actual matched
TFs on the fly. By design, that makes zero difference all when running a simple
bag-of-words query against the entire document. However, once you start using
pretty much *any* query syntax, the differences become obvious.

To discuss one, what if you limit all your searches to a single field with, and
the query is `@title foo bar`? Should the weights really depend on contents of
any other fields, as we clearly intended to limit our searches to titles? They
should not. However, with the `bm15` approximation they will. But this really is
just a performance vs quality tradeoff.

Last but not least, a couple historical quirks.

Before v.3.0.2 this factor was not-quite-correctly named `bm25` and that lasted
for just about ever. It got renamed to `bm15` in v.3.0.2. (It can be argued that
in a way it did compute the BM25 value, for a very specific `k1 = 1.2` and
`b = 0` case. But come on. There is a special name for that `b = 0` family of
cases, and it is `bm15`.)

Before v.3.5 this factor returned rounded-off int values. That caused slight
mismatches between the built-in rankers and the respective expressions. Starting
with v.3.5 it returns float values, and the mismatches are eliminated.

#### bm25a()

Document-level, parametrized, computes a value of classic `BM25(k1,b)` function
with the two given (required) parameters. For example:

```sql
SELECT ... OPTION ranker=expr('10000*bm25a(2.0, 0.7)')
```

Unlike `bm15`, this factor only account the *matching* occurrences (postings)
when computing TFs. It also requires `index_field_lengths = 1` setting to be on,
in order to compute the current and average document lengths (which is in turn
required by BM25 function with non-zero `b` parameters).

It is called `bm25a` only because `bm25` was initially taken (mistakenly) by
that `BM25(1.2, 0)` value estimate that we now (properly) call `bm15`; no other
hidden meaning in that `a` suffix.

#### bm25f()

Document-level, parametrized, computes a value of an extended `BM25F(k1,b)`
function with the two given (required) parameters, and an extra set of named
per-field weights. For example:

```sql
SELECT ... OPTION ranker=expr('10000*bm25f(2.0, 0.7, {title = 3})')
```

Unlike `bm15`, this factor only account the *matching* occurrences (postings)
when computing TFs. It also requires `index_field_lengths = 1` setting to be on.

BM25F extension lets you assign bigger weights to certain fields. Internally
those weights will simply pre-scale the TFs before plugging them into the
original BM25 formula. For the original TR, see [Zaragoza et al (1994),
"Microsoft Cambridge at TREC-13: Web and HARD tracks"][1] paper.

#### doc_word_count

Document-level, a number of unique keywords matched in the entire document.

#### field_mask

Document-level, a 32-bit mask of matched fields. Fields with numbers 33 and up
are ignored in this mask.


### Field-level ranking factors

Generally, a field-level factor is just some numeric value computed by the
ranking engine for every matched in-document text field, with regards to the
current query, describing this or this aspect of the actual match.

As a query can match multiple fields, but the final weight needs to be a single
value, these per-field values need to be folded into a single one. Meaning that,
unlike query-level and document-level factors, you can't use them directly in
your ranking formulas:

```sql
mysql> SELECT id, weight() FROM test1 WHERE MATCH('hello world')
OPTION ranker=expr('lcs');

ERROR 1064 (42000): index 'test1': field factors must only
occur within field aggregates in a ranking expression
```

The correct syntax should use one of the aggregation functions. Multiple
different aggregations are allowed:

```sql
mysql> SELECT id, weight() FROM test1 WHERE MATCH('hello world')
OPTION ranker=expr('sum(lcs) + top(max_idf) * 1000');
```

Now let's discuss the individual factors in a bit more detail.

#### atc

Field-level, Aggregate Term Closeness. This is a proximity based measure that
grows higher when the document contains more groups of more closely located and
more important (rare) query keywords.

**WARNING:** you should use ATC with `OPTION idf='plain,tfidf_unnormalized'`;
otherwise you could get rather unexpected results.

ATC basically works as follows. For every keyword *occurrence* in the document,
we compute the so called *term closeness*. For that, we examine all the other
closest occurrences of all the query keywords (keyword itself included too),
both to the left and to the right of the subject occurrence. We then compute
a distance dampening coefficient as `k = pow(distance, -1.75)` for all those
occurrences, and sum the dampened IDFs. Thus for every occurrence of every
keyword, we get a "closeness" value that describes the "neighbors" of that
occurrence. We then multiply those per-occurrence closenesses by their
respective subject keyword IDF, sum them all, and finally, compute a logarithm
of that sum.

Or in other words, we process the best (closest) matched keyword pairs in the
document, and compute pairwise "closenesses" as the product of their IDFs scaled
by the distance coefficient:
```cpp
pair_tc = idf(pair_word1) * idf(pair_word2) * pow(pair_distance, -1.75)
```

We then sum such closenesses, and compute the final, log-dampened ATC value:
```cpp
atc = log(1 + sum(pair_tc))
```

Note that this final dampening logarithm is exactly the reason you should use
`OPTION idf=plain`, because without it, the expression inside the `log()` could
be negative.

Having closer keyword occurrences actually contributes *much* more to ATC than
having more frequent keywords. Indeed, when the keywords are right next to each
other, we get `distance = 1` and `k = 1`; and when there is only one extra word
between them, we get `distance = 2` and `k = 0.297`; and with two extra words
in-between, we get `distance = 3` and `k = 0.146`, and so on.

At the same time IDF attenuates somewhat slower. For example, in a 1 million
document collection, the IDF values for 3 example keywords that are found in 10,
100, and 1000 documents would be 0.833, 0.667, and 0.500, respectively.

So a keyword pair with two rather rare keywords that occur in just 10 documents
each but with 2 other words in between would yield `pair_tc = 0.101` and thus
just barely outweigh a pair with a 100-doc and a 1000-doc keyword with 1 other
word between them and `pair_tc = 0.099`.

Moreover, a pair of two *unique*, 1-document keywords with ideal IDFs, and with
just 3 words between them would fetch a `pair_tc = 0.088` and lose to a pair of
two 1000-doc keywords located right next to each other, with a `pair_tc = 0.25`.

So, basically, while ATC does combine both keyword frequency and proximity,
it is still heavily favoring the proximity.

#### exact_field_hit

Field-level, boolean, whether the current field was (seemingly) fully covered by
the query, and in the right (query) term order, too.

This flag should be set when the field is basically either "equal" to the entire
query, or equal to a query with a few terms thrown away. Note that term order
matters, and it must match, too.

For example, if our query is `one two three`, then either `one two three`, or
just `one three`, or `two three` should all have `exact_field_hit = 1`, because
in these examples all the *field* keywords are matched by the query, and they
are in the right order. However, `three one` should get `exact_field_hit = 0`,
because of the wrong (non-query) term order. And then if we throw in any extra
terms, `one four three` field should also get `exact_field_hit = 0`, because
`four` was not matched by the query, ie. this field is not covered fully.

Also, beware that stopwords and other text processing tools might "break" this
factor.

For example, when the field is `one stop three`, where `stop` is a stopword,
we would still get 0 instead of 1, even though intuitively it should be ignored,
and the field should be kinda equal to `one three`, and we get a 1 for that.
How come?

This is because stopwords are *not* really ignored completely. They do still
affect *positions* (and that's intentional, so that matching operators and other
ranking factors would work as expected, just in some other example cases).

Therefore, this field gets indexed as `one * three`, where star marks a skipped
position. So when matching the `one two three` query, the engine knows that
positions number 1 and 3 were matched alright. But there is no (efficient) way
for it to tell what exactly was in that missed position 2 in the original field;
ie. was there a stopword, or was there any *regular* word that the query simply
did not mention (like in the `one four three` example). So when computing this
factor, we see that there was an unmatched position, therefore we assume that
the field was not covered fully (by the query terms), and set the factor to 0.

#### exact_hit

Field-level, boolean, whether a query was a full and exact match of the entire
current field (that is, after normalization, morphology, etc). Used in the SPH04
ranker.

#### exact_order

Field-level, boolean, whether all of the query keywords were matched in the
current field in the exact query order. (In other words, whether our field
"covers" the entire query, and in the right order, too.)

For example, `(microsoft office)` query would yield `exact_order = 1` in a field
with the `We use Microsoft software in our office.` content.

However, the very same query in a field with `(Our office is Microsoft free.)`
text would yield `exact_order = 0` because, while the coverage is there (all
words are matched), the order is wrong.

#### full_field_hit

Field-level, boolean, whether the current field was (seemingly) fully covered by
the query.

This flag should be set when all the *field* keywords are matched by the query,
in whatever order. In other words, this factor requires "full coverage" of the
field by the query, and "allows" to reorder the words.

For example, a field `three one` should get `full_field_hit = 1` against a query
`one two three`. Both keywords were "covered" (matched), and the order does not
matter.

Note that all documents where `exact_field_hit = 1` (which is even more strict)
must also get `full_field_hit = 1`, but not vice versa.

Also, beware that stopwords and other text processing tools might "break" this
factor, for exactly the same reasons that we discussed a little earlier in
[exact_field_hit](#exact_field_hit).

#### has_digit_hits

Field-level, total matched field hits count over just the `has_digit` keywords.

#### hit_count

Field-level, total field hits count over all keywords. In other words, total
number of keyword occurrences that were matched in the current field.

Note that a single keyword may occur (and match!) multiple times. For example,
if `hello` occurs 3 times in a field and `world` occurs 5 times, `hit_count`
will be 8.

#### is_noun_hits

Field-level, total matched field hits count over just the `is_noun` keywords.

#### is_latin_hits

Field-level, total matched field hits count over just the `is_latin` keywords.

#### is_number_hits

Field-level, total matched field hits count over just the `is_number` keywords.

#### lccs

Field-level, Longest Common Contiguous Subsequence. A length of the longest
contiguous subphrase between the query and the document, computed in keywords.

LCCS factor is rather similar to LCS but, in a sense, more restrictive. While
LCS could be greater than 1 even though no two query words are matched right
next to each other, LCCS would only get greater than 1 if there are *exact*,
contiguous query subphrases in the document.

For example, `one two three four five` query vs
`one hundred three hundred five hundred` document would yield `lcs = 3`,
but `lccs = 1`, because even though mutual dispositions of 3 matched keywords
(`one`, `three`, and `five`) do match between the query and the document, none
of the occurrences are actually next to each other.

Note that LCCS still does not differentiate between the frequent and rare
keywords; for that, see WLCCS factor.

#### lcs

Field-level, Longest Common Subsequence. This is the length of a maximum
"verbatim" match between the document and the query, counted in words.

By construction, it takes a minimum value of 1 when only "stray" keywords were
matched in a field, and a maximum value of a query length (in keywords) when the
entire query was matched in a field "as is", in the exact query order.

For example, if the query is `hello world` and the field contains these two
words as a subphrase anywhere in the field, `lcs` will be 2. Another example,
this works on *subsets* of the query too, ie. with `hello world program` query
the field that only contains `hello world` subphrase also a gets an `lcs` value
of 2.

Note that any *non-contiguous* subset of the query keyword works here, not just
a subset of adjacent keywords. For example, with `hello world program` query and
`hello (test program)` field contents, `lcs` will be 2 just as well, because
both `hello` and `program` matched in the same respective positions as they were
in the query. In other words, both the query and field match a non-contiguous
2-keyword subset `hello * program` here, hence the value of 2 of `lcs`.

However, if we keep the `hello world program` query but our field changes to
`hello (test computer program)`, then the longest matching subset is now only
1-keyword long (two subsets match here actually, either `hello` or `program`),
and `lcs` is therefore 1.

Finally, if the query is `hello world program` and the field contains an exact
match `hello world program`, `lcs` will be 3. (Hopefully that is unsurprising
at this point.

#### max_idf

Field-level, `max(idf)` over all keywords that were matched in the field.

#### max_window_hits()

Field-level, parametrized, computes `max(window_hit_count)` over all N-keyword
windows (where N is the parameter). For example:

```sql
mysql> SELECT *, weight() FROM test1 WHERE MATCH('one two')
    -> OPTION ranker=expr('sum(max_window_hits(3))');
+------+-------------------+----------+
| id   | title             | weight() |
+------+-------------------+----------+
|    1 | one two           |        2 |
|    2 | one aa two        |        2 |
|    4 | one one aa bb two |        1 |
|    3 | one aa bb two     |        1 |
+------+-------------------+----------+
3 rows in set (0.00 sec)
```

So in this example we are looking at rather short 3-keyword windows, and in
document number 3 our matched keywords are too far apart, so the factor is 1.
However, in document number 4 the `one one aa` window has 2 occurrences (even
though of just one keyword), so the factor is 2 there. Documents number 1 and 2
are straightforward.

#### min_best_span_pos

Field-level, the position of the first maximum LCS keyword span.

For example, assume that our query was `hello world program`, and that the
`hello world` subphrase was matched twice in the current field, in positions
13 and 21. Now assume that `hello` and `world` additionally occurred elsewhere
in the field (say, in positions 5, 8, and 34), but as those occurrences were not
next to each other, they did not count as a subphrase match. In this example,
`min_best_span_pos` will be 13, ie. the position of a first occurrence of
a longest (maximum) match, LCS-wise.

Note how for the single keyword queries `min_best_span_pos` must always equal
`min_hit_pos`.

#### min_gaps

Field-level, the minimum number of positional gaps between (just) the keywords
matched in field. Always 0 when less than 2 keywords match; always greater or
equal than 0 otherwise.

For example, with the same `big wolf` query, `big bad wolf` field would yield
`min_gaps = 1`; `big bad hairy wolf` field would yield `min_gaps = 2`;
`the wolf was scary and big` field would yield `min_gaps = 3`; etc. However,
a field like `i heard a wolf howl` would yield `min_gaps = 0`, because only one
keyword would be matching in that field, and, naturally, there would be no gaps
*matched* keywords.

Therefore, this is a rather low-level, "raw" factor that you would most likely
want to *adjust* before actually using for ranking.

Specific adjustments depend heavily on your data and the resulting formula, but
here are a few ideas you can start with:

  * any `min_gaps` based boosts could be simply ignored when `word_count < 2`;
  * non-trivial `min_gaps` values (ie. when `word_count <= 2`) could be clamped
    with a certain "worst case" constant while trivial values (ie. when
    `min_gaps = 0` and `word_count < 2`) could be replaced by that constant;
  * a transfer function like `1 / (1 + min_gaps)` could be applied (so that
    better, smaller min_gaps values would maximize it and worse, bigger
    `min_gaps` values would fall off slowly).

#### min_hit_pos

Field-level, the position of the first matched keyword occurrence, counted in
words. Positions begins from 1, so `min_hit_pos = 0` must be impossible in
an actually matched field.

#### min_idf

Field-level, `min(idf)` over all keywords (not occurrences!) that were matched
in the field.

#### phrase_decay10

Field-level, position-decayed (0.5 decay per 10 positions) and proximity-based
"similarity" of a matched field to the query interpreted as a phrase.

Ranges from 0.0 to 1.0, and maxes out at 1.0 when the entire field is a query
phrase repeated one or more times. For instance, `[cats dogs]` query will yield
`phrase_decay10 = 1.0` against `title = [cats dogs cats dogs]` field (with two
repeats), or just `title = [cats dogs]`, etc.

Note that `[dogs cats]` field yields a smaller `phrase_decay10` because of no
phrase match. The exact value is going to vary because it also depends on IDFs.
For instance:

```sql
mysql> select id, title, weight() from rt
    -> where match('cats dogs')
    -> option ranker=expr('sum(phrase_decay10)');
+--------+---------------------+------------+
| id     | title               | weight()   |
+--------+---------------------+------------+
| 400001 | cats dogs           |        1.0 |
| 400002 | cats dogs cats dogs |        1.0 |
| 400003 | dogs cats           | 0.87473994 |
+--------+---------------------+------------+
3 rows in set (0.00 sec)
```

The signal calculation is somewhat similar to ATC. We begin with assigning
an exponentially discounted, position-decayed IDF weight to every matched hit.
The number 10 in the signal name is in fact the half-life distance, so that
the decay coefficient is 1.0 at position 1, 0.5 at position 11, 0.25 at 21, etc.
Then for each adjacent hit we multiply the per-hits weights and obtain the pair
weight; compute an expected adjacent hit position (ie. where it should had been
in the ideal phrase match case); and additionally decay the pair weight based
on the difference between the expected and actual position. In the end, we also
perform normalization so that the signal fits into 0 to 1 range.

To summarize, the signal decays when hits are more sparse and/or in a different
order in the field than in the query, and also decays when the hits are farther
from the beginning of the field, hence the "phrase_decay" name.

Note that this signal calculation is relatively heavy, also similarly to `atc`
signal. Even though we actually did not observe any significant slowdowns on our
production workloads, neither on average nor at 99th percentile, your mileage
may vary, because our synthetic *worst case* test queries were significantly
slower on our tests, up to 2x and more in extreme cases. For that reason we also
added `no_decay=1` flag to `FACTORS()` that lets you skip computing this signal
at all if you do not actually use it.

#### phrase_decay30

Field-level, position-decayed (0.5 decay per 30 positions) and proximity-based
"similarity" of a matched field to the query interpreted as a phrase.

Completely similar to `phrase_decay10` signal, except that the position-based
half-life is 30 rather than 10. In other words, `phrase_decay30` decays somewhat
slower based on the in-field position (for example, decay coefficient is going
to be 0.5 rather than 0.125 at position 31). Therefore it penalizes more
"distant" matches less than `phrase_decay10` would.

#### sum_idf

Field-level, `sum(idf)` over all keywords (not occurrences!) that were matched
in the field.

#### sum_idf_boost

Field-level, `sum(idf_boost)` over all keywords (not occurrences!) that were
matched in the field.

#### tf_idf

Field-level, a sum of `tf*idf` over all the keywords matched in the field.
(Or, naturally, a sum of `idf` over all the matched postings.)

For the record, `TF` is the Term Frequency, aka the number of (matched) keyword
occurrences in the current field.

And `IDF` is the Inverse Document Frequency, a floating point value between 0
and 1 that describes how frequent this keyword is in the index.

Basically, frequent (and therefore *not* really interesting) words get lower
IDFs, hitting the minimum value of 0 when the keyword is present in all of the
indexed documents. And vice versa, rare, unique, and therefore interesting words
get higher IDFs, maxing out at 1 for unique keywords that occur in just a single
document.

#### trf_aqt

Field-level, float, a fraction of alphanumeric-only query trigrams matched by
the field trigrams filter. Takes values in 0..1 range.

See ["Ranking: trigrams"](#ranking-trigrams) section for more details.

#### trf_i2f

Field-level, float, a ratio of query-and-field intersection filter bitcount to
field filter bitcount (Intersection to Field). Takes values in 0..1 range.

See ["Ranking: trigrams"](#ranking-trigrams) section for more details.

#### trf_i2q

Field-level, float, a ratio of query-and-field intersection filter bitcount to
query filter bitcount (Intersection to Query). Takes values in 0..1 range.

See ["Ranking: trigrams"](#ranking-trigrams) section for more details.

#### trf_i2u

Field-level, float, a ratio of query-and-field intersection filter bitcount to
query-or-field union filter bitcount (Intersection to Union). Takes values in
0..1 range.

See ["Ranking: trigrams"](#ranking-trigrams) section for more details.

#### trf_naqt

Field-level, float, a number of alphanumeric-only query trigrams matched by
the field trigrams filter. Takes non-negative integer values (ie. 0, 1, 2, etc),
but stored as float anyway, for consistency.

See ["Ranking: trigrams"](#ranking-trigrams) section for more details.

#### trf_qt

Field-level, float, a fraction of query trigrams matched by the field trigrams
filter. Either in 0..1 range, or -1 when there is no field filter.

See ["Ranking: trigrams"](#ranking-trigrams) section for more details.

#### user_weight

Field-level, a user specified per-field weight (for a bit more details on how
to set those, refer to [`OPTION field_weights`](sphinx2.html#sphinxql-select)
section). By default all these weights are set to 1.

#### wlccs

Field-level, Weighted Longest Common Contiguous Subsequence. A sum of IDFs over
the keywords of the longest contiguous subphrase between the current query and
the field.

WLCCS is computed very similarly to LCCS, but every "suitable" keyword
occurrence increases it by the keyword IDF rather than just by 1 (which is the
case with both LCS and LCCS). That lets us rank sequences of more rare and
important keywords higher than sequences of frequent keywords, even if the
latter are longer. For example, a query `Zanzibar bed and breakfast` would yield
`lccs = 1` against a `hotels of Zanzibar` field, but `lccs = 3` against
a `London bed and breakfast` field, even though `Zanzibar` could be actually
somewhat more rare than the entire `bed and breakfast` phrase. WLCCS factor
alleviates (to a certain extent) by accounting the keyword frequencies.

#### word_count

Field-level, the number of unique keywords matched in the field. For example,
if both `hello` and `world` occur in the current field, `word_count` will be 2,
regardless of how many times do both keywords occur.


Ranking: built-in ranker formulas
---------------------------------

All of the built-in Sphinx lightweight rankers can be reproduced using the
expression based ranker. You just need to specify a proper formula in the
`OPTION ranker` clause.

This is definitely going to be (significantly) slower than using the built-in
rankers, but useful when you start fine-tuning your ranking formulas using one
of the built-in rankers as your baseline.

(Also, the formulas define the nitty gritty built-in ranker details in a nicely
readable fashion.)

| Ranker         | Formula                                                                  |
|----------------|--------------------------------------------------------------------------|
| PROXIMITY_BM15 | `sum(lcs*user_weight)*10000 + bm15`                                      |
| BM15           | `bm15`                                                                   |
| NONE           | `1`                                                                      |
| WORDCOUNT      | `sum(hit_count*user_weight)`                                             |
| PROXIMITY      | `sum(lcs*user_weight)`                                                   |
| MATCHANY       | `sum((word_count + (lcs - 1)*max_lcs)*user_weight)`                      |
| FIELDMASK      | `field_mask`                                                             |
| SPH04          | `sum((4*lcs + 2*(min_hit_pos==1) + exact_hit)*user_weight)*10000 + bm15` |

And here goes a complete example query:

```sql
SELECT id, weight() FROM test1
WHERE MATCH('hello world')
OPTION ranker=expr('sum(lcs*user_weight)*10000 + bm15')
```


Ranking: IDF magics
--------------------

Sphinx supports several different IDF (Inverse Document Frequency) calculation
options. Those can affect your relevance ranking (aka scoring) when you are:

  * *either* sharding your data, even with built-in rankers;
  * *or* doing any custom ranking work, even on a single shard.

By default, term IDFs are (a) per-shard, and (b) computed online. So they might
fluctuate significantly when ranking. And several other ranking factors rely on
them, so the entire rank might change a lot in a seeimingly random fashion.
The reasons are twofold.

First, IDFs usually differ across shards (ie. individual indexes that make up
a bigger combined index). This means that a completely identical document might
rank differently depending on a specific shard it ends up in. Not great.

Second, IDFs might change from query to query, as you update the index data.
That instability in time might or might not be a desired effect.

To help alleviate these quirks (if they affect your use case), Sphinx offers two
features:

  1. `local_df` option to aggregate sharded IDFs.
  2. `global_idf` feature to enforce prebuilt static IDFs.

`local_df` syntax is `SELECT ... OPTION local_df=1` and enabling that option
tells the query to compute IDFs (more) precisely, ie. over the entire index
rather than individual shards. The default value is 0 (off) for performance
reasons.

`global_idf` feature is more complicated and includes several components:

  * `indextool dumpdict --stats` command that generates the source data, ie.
    the per-shard dictionary dumps;
  * `indextool buildidf` command that builds a static IDF file from those;
  * per-shard `global_idf` config directive that lets you assign a static IDF
    file to your shards;
  * per-query `OPTION global_idf=1` that forces the query to use that file.

Both these features affect the input variables used for IDF calculations. More
specifically:

  * let `n` be the DF, document frequency (for a given term);
  * let `N` be the corpus size, total number of documents;
  * by default, both `n` and `N` are per-shard;
  * with `local_df`, they both are summed across shards;
  * with `global_idf`, they both are taken from a static IDF file.

The static `global_idf` file actually stores a bunch of `n` values for every
individual term, and the `N` value for the entire corpus, summed over all the
source files that were available during `--buildidf` stage. For terms that are
not present in the static `global_idf` file, their current (dynamic) DF values
will be used. `local_df` should also still affect those.

To avoid overflows, `N` is adjusted up for the actual corpus size. Meaning that,
for example, if the `global_idf` file says there were 1000 documents, but your
index carries 3000 documents, then `N` is set to the bigger value, ie. 3000.
Therefore, you should either avoid using too small data slices for dictionary
dumps, and/or manually adjust the frequencies, otherwise your static IDFs might
be quite off.

To keep the `global_idf` file reasonably compact, you can use the additional
`--skip-uniq` switch when doing the `--buildidf` stage. That switch will filter
out all terms that only occur once. That usually reduces the `.idf` file size
greatly, while still yielding exact or almost-exact results.

### How Sphinx computes IDF

In v.3.4 we finished cleaning the legacy IDF code. Before, we used to support
two different methods to compute IDF, and we used to have dubious IDF scaling.
All that legacy is now gone, finally and fully, and we do not plan any further
significant changes.

Nowadays, Sphinx always uses the following formula to compute IDF from `n`
(document frequency) and `N` (corpus size).

  * `idf = min(log(N/n), IDF_LIMIT) * term_idf_boost`
  * `IDF_LIMIT` is currently hardcoded at 20.0

So we start with de-facto standard `raw_idf = log(N/n)`; then clamp it with
`IDF_LIMIT` (and stop differentiating between extremely rare keywords); then
apply per-term user boosts from the query.

Note how with the current limit of 20.0 "extremely rare" *specifically* means
that just the keywords that occur less than once per as much as ~485.2 million
tokens will be considered "equal" for ranking purposes. We may eventually change
this limit.

`term_idf_boost` naturally defaults to `1.0` but can be changed for individual
query terms by using the respective [keyword modifier](#keyword-modifiers), eg.
`... WHERE MATCH('cat^1.2 dog')`.


Ranking: field lengths
-----------------------

BM25 and BM25F ranking functions require both per-document and index-average
field lengths as one of their inputs. Otherwise they degrade to a simpler, less
powerful BM15 function.

For the record, lengths can be computed in different units here, normally either
bytes, or characters, or tokens. Leading to (slightly) different variants of the
BM functions. Each approach has its pros and cons. In Sphinx we choose to have
our lengths in tokens.

Now, with `index_field_lengths = 1` Sphinx automatically keeps track of all
those lengths on the fly. Per-document lengths are stored and index-wide totals
are updated on every index write. And then those (dynamic!) index-wide totals
are used to compute averages for BMs on every full-text search.

Yet sometimes those are *too* dynamic, and you might require *static* averages
instead. Happens for a number of various reasons. For one, "merely" to ensure
consistency between training data and production indexes. Or, ensure identical
BM25s over different cluster nodes. Pretty legit.

`global_avg_field_lengths` index setting does exactly that. It lets you specify
**static index-average field lengths for BM25 calculations**.

Note that you still need `index_field_lengths` enabled because BM25 requires
both per-document lengths *and* index-average lengths. The new setting only
specifies the latter.

The setting is per-index, so different values can be specified for different
indexes. It takes a comma-separated list of `field: weight` pairs, as follows.

```bash
index test1
{
    ...
    global_avg_field_lengths = title: 1.23, content: 45.67
}
```

For now Sphinx considers it okay to *not* specify a length here. The unlisted
fields lengths are set to 0.0 by default. Think of system fields that should not
even be ranked. Those need no extra config.

However, when you **do** specify a field, you **must** specify an existing one.
Otherwise, that's an error.

Using `global_idf` and `global_avg_field_lengths` in concert enables fully
"stable" BM25 calculations. With these two settings, most BM25 values should
become completely repeatable, rather than jittering a bit (or a lot) over time
from write to write, or across instances, or both.

Here's an example with two indexes, `rt1` and `rt2`, where the second one only
differs in that we have `global_avg_field_lengths` enabled. After the first 3
inserts we get this.

```sql
mysql> select id, title, weight() from rt1 where match('la')
    -> option ranker=expr('bm25a(1.2,0.7)');
+------+----------------------------------+-----------+
| id   | title                            | weight()  |
+------+----------------------------------+-----------+
|    3 | che la diritta via era smarrita  | 0.5055966 |
+------+----------------------------------+-----------+
1 row in set (0.00 sec)

mysql> select id, title, weight() from rt2 where match('la')
    -> option ranker=expr('bm25a(1.2,0.7)');
+------+----------------------------------+------------+
| id   | title                            | weight()   |
+------+----------------------------------+------------+
|    3 | che la diritta via era smarrita  |  0.2640895 |
+------+----------------------------------+------------+
1 row in set (0.00 sec)
```

The BM25 values differ as expected, because dynamic averages in `rt1` differ
from the specific static ones in `rt2`, but let's what happens after just a few
more rows.

```sql
mysql> select id, title, weight() from rt1 where match('la') and id=3
    -> option ranker=expr('bm25a(1.2,0.7)');
+------+----------------------------------+-----------+
| id   | title                            | weight()  |
+------+----------------------------------+-----------+
|    3 | che la diritta via era smarrita  | 0.5307667 |
+------+----------------------------------+-----------+
1 row in set (0.00 sec)

mysql> select id, title, weight() from rt2 where match('la') and id=3
    -> option ranker=expr('bm25a(1.2,0.7)');
+------+----------------------------------+------------+
| id   | title                            | weight()   |
+------+----------------------------------+------------+
|    3 | che la diritta via era smarrita  |  0.2640895 |
+------+----------------------------------+------------+
2 rows in set (0.00 sec)
```

Comparing these we see how the dynamic averages in `rt1` caused BM25 to shift
from 0.506 to 0.531 while the static `global_avg_field_lengths` in `rt2` kept
BM25 static too. And repeatable. That's exactly what this setting is about.


Ranking: picking fields with `rank_fields`
-------------------------------------------

When your indexes and queries contain any special "fake" keywords (usually used
to speedup matching), it makes sense to exclude those from ranking. That can be
achieved by putting such keywords into special fields, and then using `OPTION
rank_fields` clause in the `SELECT` statement to pick the fields with actual
text for ranking. For example:

```sql
SELECT id, weight(), title FROM myindex
WHERE MATCH('hello world @sys _category1234')
OPTION rank_fields='title content'
```

`rank_fields` is designed to work as follows. Only the keyword occurrences in
the ranked fields get processed when computing ranking factors. Any other
occurrences are ignored (by ranking, that is).

Note a slight caveat here: for *query-level* factors, only the *query* itself
can be analyzed, not the index data.

This means that when you do not explicitly specify the fields in the query, the
query parser *must* assume that the keyword can actually occur anywhere in the
document. And, for example, `MATCH('hello world _category1234')` will compute
`query_word_count=3` for that reason. This query does indeed have 3 keywords,
even if `_category1234` never *actually* occurs anywhere except `sys` field.

Other than that, `rank_fields` is pretty straightforward. *Matching* will still
work as usual. But for *ranking* purposes, any occurrences (hits) from the
"system" fields can be ignored and hidden.


Ranking: using different keywords than matching {#xfactors}
------------------------------------------------------------

Text ranking signals are usually computed using `MATCH()` query keywords.
However, sometimes matching and ranking would need to diverge. To support that,
starting from v.3.5 you can **explicitly specify a set of keywords to rank** via
a text argument to `FACTORS()` function.

Moreover, that works even when there is no `MATCH()` clause at all. Meaning that
you can now **match by attributes only, and then rank matches by keywords**.

Examples!

```sql
# match with additional special keywords, rank without them
SELECT id, FACTORS('hello world') FROM myindex
WHERE MATCH('hello world @location locid123')
OPTION ranker=expr('1')

# match by attributes, rank those matches by keywords
SELECT id, FACTORS('hello world') FROM myindex
WHERE location_id=123
OPTION ranker=expr('1')
```

These two queries match documents quite differently, and they will return
different sets of documents, too. Still, the matched documents in both sets must
get *ranked* identically, using the provided keywords. That is, for any document
that makes it into any of the two result sets, `FACTORS()` gets computed as if
that document was matched using `MATCH('hello world')`, no matter what the
actual `WHERE` clause looked like.

We refer to the keywords passed to `FACTORS()` as **the ranking query**, while
the keywords and operators from the `MATCH()` clause are **the matching query**.

**Explicit ranking queries are treated as BOWs**, ie. bags-of-words. Now, some
of our ranking signals do account for the "in-query" keyword positions, eg. LCS,
to name one. So **BOW keyword order still matters**, and randomly shuffling the
keywords may and will change (some of) the ranking signals.

But other than that, **there is no syntax support in the ranking queries**, and
that creates two subtle differences from the matching queries.

  1. Human-readable operators are considered keywords.
  2. Operator NOT is ignored rather than accounted.

Re human-readable operators, consider `cat MAYBE dog` query. `MAYBE` is a proper
matching operator according to `MATCH()` query syntax, and the default BOW used
for ranking will have two keywords, `cat` and `dog`. But with `FACTORS()` that
`MAYBE` also gets used for ranking, so we get three keywords in a BOW that way:
`cat`, `maybe`, `dog`.

Re operator NOT, consider `year -end` (with a space). Again, `MATCH()` syntax
dictates that `end` is an excluded term here, so the default BOW is just `year`,
while the `FACTORS()` BOW is `year` and `end` both.

Bottom line, **avoid using Sphinx query syntax in ranking queries**. Queries
with full-text operators may misbehave. Those are intended for `MATCH()` only.
On the other hand, passing end-user syntax-less queries to `FACTORS()` should be
a breeze! Granted, those queries need some sanitizing anyway, as long as you use
them in `MATCH()` too, which ones usually does. Fun fact, even that sanitizing
should not be really needed for `FACTORS()` though.

Now, unlike syntax, **morphology is fully supported in the ranking queries**.
Exceptions, mappings, stemmers, lemmatizers, user morphology dictionaries, all
that jazz is expected to work fine.

**Ranking query keywords can be arbitrary.** You can rank the document anyhow
you want. Matching becomes unrelated and does not impose any restrictions.

As an important corollary, **documents may now have 0 ranking keywords**, and
therefore **signals may now get completely zeroed out** (but only with the new
ranking queries, of course). The `doc_word_count` signal is an obvious example.
Previously, you would *never* ever see a zero `doc_word_count`, now that can
happen, and **your ranking formulas or ML models may need updating**.

```sql
# good old match is still good, no problem there
SELECT id, WEIGHT()
FROM myindex WHERE MATCH('hello world')
OPTION ranker=expr('1/doc_word_count')

# potential division by zero!
SELECT id, WEIGHT(), FACTORS('workers unite')
FROM myindex WHERE MATCH('hello world')
OPTION ranker=expr('1/doc_word_count')
```

And to reiterate just once, **you can completely omit the matching text query**
(aka the `MATCH()` clause), and still have the retrieved documents ranked.
**Match by attributes, rank by keywords**, now legal, whee!

```sql
SELECT id, FACTORS('lorem ipsum'), id % 27 AS val
FROM myindex WHERE val > 10
OPTION ranker=expr('1')
```

Finally, there are a few more rather specific and subtle restrictions related to
ranking queries.

  - Expression ranker (`OPTION ranker=expr('...')`) is required.
  - The same ranking query across all `FACTORS()` instances is required.
  - When there is no `MATCH()` clause, "direct" filtering or sorting by values
    that depend on `FACTORS()` is forbidden. You can use subselects for that.

```sql
# NOT OK! different ranking queries, not supported
SELECT id,
  udf1(factors('lorem ipsum')) AS w1,
  udf2(factors('dolor sit')) AS w2
FROM idx

# NOT OK! filtering on factors() w/o match() is forbidden
SELECT id, rankudf(factors('lorem ipsum')) AS w
FROM idx WHERE w > 0

# NOT OK! sorting on factors() w/o match() is forbidden
SELECT id, rankudf(factors('lorem ipsum')) AS w
FROM idx ORDER BY w DESC

# ok, but we can use subselect to workaround that
SELECT * FROM (
SELECT id, rankudf(factors('lorem ipsum')) AS w FROM idx
) WHERE w > 0

# ok, sorting on factors() with match() does work
SELECT id, rankudf(factors('lorem ipsum')) AS w
FROM idx WHERE MATCH('dolor sit') ORDER BY w DESC
```


Ranking: trigrams
-----------------

Signals based on character trigrams are useful to improve ranking for short
fields such as document titles. But the respective ranking gains are not that
huge. Naively using full and exact trigram sets (and thus exact signals) is,
basically, way too expensive to justify those gains.

However, we found that using **coarse trigram sets**  (precomputed and stored
as **tiny Bloom filters**) also yields measurable ranking improvements, while
having only a very small impact on performance: about just 1-5% extra CPU load
both when indexing and searching. So we added trigram indexing and ranking
support based on that.

Here's a quick overview of the essentials.

  * When indexing, we can now compute and store a per-field "trigram filter",
    ie. a tiny Bloom filter *coarsely* representing the field text trigrams.

  * Note that trigram (filters) indexing is optional and must be enabled
    explicitly, using the `index_trigram_fields` directive.

  * When searching, we use those filters (where available) to compute a few
    additional trigram ranking signals.

  * Trigram signals are accessible via `FACTORS()` function as usual; all their
    names begin with a `trf_` prefix (TRF means Trigram Filter).

  * Note that trigram signals are *always* available to both ranking expressions
    and UDFs, but for fields without trigram filters, they are all zeroed out
    (except for `trf_qt` which equals -1 in that case).

That's basically all the high-level notes; now let's move on to the nitty-gritty
details.

As mentioned, trigram filter indexing is enabled by `index_trigram_fields`
directive, for example:

```bash
index_trigram_fields = title, keywords
```

Both plain and RT indexes are supported. The Bloom filter size is currently
hardcoded at 128 bits (ie. 16 bytes) per each field. The filters are stored as
hidden system document attributes.

Expression ranker (ie. `OPTION ranker=expr(...)`) then checks for such filters
when searching, and computes a few extra signals for fields that have them. Here
is a brief reference table.

| Signal   | Description                                                 |
|----------|-------------------------------------------------------------|
| trf_qt   | Fraction of Query Trigrams present in field filter          |
| trf_i2u  | Ratio of Intersection to Union filter bitcounts             |
| trf_i2q  | Ratio of Intersection to Query filter bitcounts             |
| trf_i2f  | Ratio of Intersection to Field filter bitcounts             |
| trf_aqt  | Fraction of Alphanum Query Trigrams present in field filter |
| trf_naqt | Number of Alphanum Query Trigrams                           |

Trigrams are computed over almost raw field and query text. "Almost raw" means
that we still apply `charset_table` for case folding, but perform no other text
processing. Even the special characters should be retained.

Trigrams sets are then heavily pruned, again both for field and query text, and
then squashed into Bloom filters. This step makes our internal representations
quite coarse.

However, it also ensures that even the longer input texts never overflow the
resulting filter. Pruning only keeps a few select trigrams, and the exact limit
is derived based on the filter size. So that the false positive rate after
compressing the pruned trigrams into a filter is still reasonable.

That's rather important, because in all the signal computations the engine uses
those coarse values, ie. pruned trigram sets first, and then filters built from
those next. Meaning that signals values are occasionally way off from what one
would intuitively expect. Note that for very short input texts (say, up to 10-20
characters) the filters could still yield exact results. But that can not be
*guaranteed*; not even for texts that short.

That being said, the new trigram signals are specifically computed as follows.
Let's introduce the following short names:

 * `qt`, set of query trigrams (also pruned, same as field trigrams)
 * `aqt`, subset of alphanumeric-only query trigrams
 * `QF`, query trigrams filter (built from `qt`)
 * `FF`, field trigrams filter
 * `popcount()`, population count, ie. number of set bits (in a filter)

In those terms, the signals are computed as follows:

```python
trf_qt = len([x for x in qt where FF.probably_has(x)]) / len(qt)
trf_i2u = popcount(QF & FF) / popcount(QF | FF)
trf_i2q = popcount(QF & FF) / popcount(QF)
trf_i2f = popcount(QF & FF) / popcount(FF)
```

So-called "alphanum" trigrams are extracted from additionally filtered query
text, keeping just the terms completely made of latin alphanumeric characters
(ie. `[a-z0-9]` characters only), and ignoring any other terms (ie. with special
characters, or in national languages, etc).

```python
trf_aqt = len([x for x in aqt where FF.probably_has(x)]) / len(aqt)
trf_naqt = len(aqt)
```

Any divisions by zero must be checked and must return 0.0 rather than infinity.

Naturally, as almost all these signals (except `trf_naqt`) are ratios, they are
floats in the 0..1 range.

However, the leading `trf_qt` ratio is at the moment also reused to signal that
the trigram filter is not available for the current field. In that case it gets
set to -1. So you want to clamp it by zero in your ranking formulas and UDFs.

All these signals are always accessible in both ranking expressions and UDFs,
even if the index was built without trigrams. However, for brevity they are
suppressed from the `FACTORS()` output:

```sql
mysql> select id, title, pp(factors()) from index_no_trigrams
    -> where match('Test It') limit 1
    -> option ranker=expr('sum(lcs)*10000+bm15') \G
*************************** 1. row ***************************
           id: 2702
        title: Flu....test...
pp(factors()): {
  "bm15": 728,
...
  "fields": [
    {
      "field": 0,
      "lcs": 1,
...
      "is_number_hits": 0,
      "has_digit_hits": 0
    },
...
}


mysql> select id, title, pp(factors()) from index_title_trigrams
    -> where match('Test It') limit 1
    -> option ranker=expr('sum(lcs)*10000+bm15') \G
*************************** 1. row ***************************
           id: 2702
        title: Flu....test...
pp(factors()): {
  "bm15": 728,
...
  "fields": [
    {
      "field": 0,
      "lcs": 1,
...
      "is_number_hits": 0,
      "has_digit_hits": 0,
      "trf_qt": 0.666667,
      "trf_i2u": 0.181818,
      "trf_i2q": 0.666667,
      "trf_i2f": 0.200000,
      "trf_aqt": 0.666667,
      "trf_naqt": 3.000000
    },
...
}
```

Note how in the super simple example above the ratios are rather as expected,
after all. Query and field have just 3 trigrams each ("it" also makes a trigram,
despite being short). All text here is alphanumeric, 2 out of 3 trigrams match,
and all the respective ratios are 0.666667, as they should.


Ranking: clickstats
--------------------

Starting with v.3.5 Sphinx lets you compute a couple static per-field signals
(`xxx_tokclicks_avg` and `xxx_tokclicks_sum`) and one dynamic per-query signal
(`words_clickstat`) based on per-keyword "clicks" statistics, or "clickstats"
for short.

Basically, clickstats work as follows.

At indexing time, for all the "interesting" keywords, you create a simple
3-column TSV table with the keywords, and per-keyword "clicks" and "events"
counters. You then bind that table (or multiple tables) to fields using
`index_words_clickstat_fields` directive, and `indexer` computes and stores 2
per-field floats, `xxx_tokclicks_avg` and `xxx_tokclicks_sum`, where `xxx` is
the field name.

At query time, you use `query_clickstats` directive to have `searchd` apply the
clickstats table to queries, and compute per-query signal, `words_clickstat`.

While these signals are quite simple, we found that they do improve our ranking
models. Now, more details and examples!

**Clickstats TSV file format.** Here goes a simple example. Quick reminder, our
columns here are "keyword", "clicks", and "events".

```
# WARNING: spaces here in docs because Markdown can't tabs
mazda   100 200
toyota  150 300
```

To avoid noisy signals, you can zero them out for fields (or queries) where
`sum(events)` is lower than a given threshold. To configure that threshold, use
the following syntax:

```
# WARNING: spaces here in docs because Markdown can't tabs
$COUNT_THRESHOLD    20
mazda   100 200
toyota  150 300
```

You can reuse one TSV table for everything, or you can use multiple separate
tables for individual fields and/or queries.

**Config directives format.** The indexing-time directive should contain a small
dictionary that binds individual TSV tables to fields:
```bash
index_words_clickstat_fields = title:t1.tsv, body:t2.tsv
```

The query-time directive should simply mention the table:
```bash
query_words_clickstat = qt.tsv
```

**Computed (static) attributes and (dynamic) query signal.** Two static
autocomputed attributes, `xxx_tokclicks_avg` and `xxx_tokclicks_sum`, are
defined as `avg(clicks/events)` and `sum(clicks)` respectively, over all the
postings found in the `xxx` field while indexing.

Dynamic `words_clickstat` signal is defined as `sum(clicks)/sum(events)` over
all the postings found in the current query.


Ranking: tokhashes and `wordpair_ctr`
--------------------------------------

Starting with v.3.5 Sphinx can build internal field token hashes ("tokhashes"
for short) while indexing, then utilize those for ranking. To enable tokhashes,
just add the following directive to your index config.

```bash
index_tokhash_fields = title, keywords
```

Keep in mind that tokhashes are stored as attributes, and therefore require
additional disk and RAM. They are intended for short fields like titles where
that should not be an issue. Also, tokhashes are based on raw tokens (keywords),
ie. hashes are stored before morphology.

The first new signal based on tokhashes is `wordpair_ctr` and it computes
`sum(clicks) / sum(views)` over all the matching `{query_token, field_token}`
pairs. This is a per-field signal that only applies to tokhash-indexed fields.
It also requires that you configure a global wordpairs table for `searchd` using
the `wordpairs_ctr_file` directive in `searchd` section.

The table must be in TSV format (tab separated) and it must contain 4 columns
exactly: **query_token, field_token, clicks, views**. Naturally, clicks must not
be negative, and views must be strictly greater than zero. Bad lines failing
to meet these requirements are ignored. Empty lines and comment lines (starting
with `#` sign) are allowed.

```bash
# in sphinx.conf
searchd
{
    wordpairs_ctr_file = wordpairs.tsv
    ...
}

# in wordpairs.tsv
# WARNING: spaces here in docs because Markdown can't tabs
# WARNING: MUST be single tab separator in prod!
whale   blue    117 1000
whale   moby    56  1000
angels  blue    42  1000
angels  red     3   1000
```

So in this example when we query for `whale`, documents that mention `blue` in
their respective tokhash fields must get `wordpair_ctr = 0.117` in those fields,
documents with `moby` must get `wordpair_ctr = 0.056`, etc.

Current implementation is that at most 100 "viable" wordpairs (ie. ones with
"interesting" query words from the 1st column) are looked up. This is to avoid
performance issues when there are too many query and/or field words. Both this
straightforward "lookup them all" implementation and the specific limit may
change in the future.

Note that a special value `wordpair_ctr = -1` must be handled as NULL in your
ranking formulas or UDFs. Zero value means that `wordpair_ctr` is defined, but
computes to zero. A value of -1 means NULL in a sense that `wordpair_ctr` is not
even defined (not a tokhash field, or no table configured). `FACTORS()` output
skips the `wordpair_ctr` key in this case. One easy way to handle -1 is to
simply clamp it by 0.

You can also impose a minimum `sum(views)` threshold in your wordpairs table as
follows.

```bash
$VIEWS_THRESHOLD    100
```

Values that had `sum(views) < $VIEWS_THRESHOLD` are zeroed out. By default this
threshold is set to 1 and any non-zero sum goes. Raising it higher is useful to
filter out weak/noisy ratios.

Last but not least, note that everything (clicks, views, sums, etc) is currently
computed in signed 32-bit integers, and overflows at INT_MAX. Beware.


Ranking: token classes
-----------------------

Starting with v.3.5 you can configure a number of (raw) token classes, and have
Sphinx compute per-field and per-query token class bitmasks.

Configuring this requires just 2 directives, `tokclasses` to define the classes,
and `index_tokclass_fields` to tag the "interesting" fields.

```bash
# somewhere in sphinx.conf
index tctest
{
    ...
    tokclasses = 0:colors.txt, 3:articles.txt, 7:swearing.txt
    index_tokclass_fields = title
}

# cat colors.txt
red orange yellow green
blue indigo violet

# cat articles.txt
a
an
the
```

**The tokclass values are bit masks of the matched classes.** As you can see,
`tokclasses` contains several entries, each with a class number and a file name.
Now, the class number is a mask bit position. The respective mask bit gets set
once any (raw) token matches the class.

So tokens from `colors.txt` will have bit 0 in the per-field mask set, tokens
from `articles.txt` will have bit 3 set, and so on.

**Per-field tokclasses are computed when indexing.** Raw tokens from fields
listed in `index_tokclass_fields` are matched against classes from `tokclasses`
while indexing. The respective `tokclass_xxx` mask attribute gets automatically
created for every field from the list. The attribute type is `UINT`.

**Query tokclass is computed when searching.** And `FACTORS()` now returns a new
`query_tokclass_mask` signal with that.

To finish off the bits and masks and values topic, let's dissect a small example.

```sql
mysql> SELECT id, title, tokclass_title FROM tctest;
+------+--------------------------+--------------+
| id   | title                  | tokclass_title |
+------+------------------------+----------------+
|  123 | the cat in the red hat |              9 |
|  234 | beige poodle           |              0 |
+------+------------------------+----------------+
2 rows in set (0.00 sec)
```

We get `tokclass_title = 9` computed from `the cat in the red hat` title here,
seeing as `the` belongs to class 3 and `red` to class 0. The bitmask with bits
0 and 3 set yields 9, because `(1 << 0) + (1 << 3) = 1 + 8 = 9`. The other title
matches no interesting tokens, hence we get `tokclass_title = 0` from that one.

Likewise, a query with "swearing" and "articles" (but no "colors") would yield
`query_tokclass_mask` to 129, because bits 7 and 0 (with values 128 and 1) would
get set for any tokens from "swearing" and "articles" lists. And so on.

**The maximum allowed number of classes is 30**, so class numbers 0 to 29
(inclusive) are accepted. Other numbers should fail.

**The maximum `tokclasses` text file line length is 4096**, the remainder is
truncated, so don't put all your tokens on one huge line.

**Tokens may belong to multiple classes**, and multiple bits will then be set.

`query_tokclass_mask` with all bits set, ie. -1 signed or 4294967295 unsigned,
**must be interpreted as a null value** in ranking UDFs and formulas.

**Token classes are designed for comparatively "small" lists**. Think lists of
articles, prepositions, colors, etc. Thousands of entries are quite okay,
millions less so. While there aren't any size limits just yet, take note that
huge lists may impact performance here.

For one, **all tokens classes are always fully stored in the index header**,
ie. those text files *contents* from `tokclasses` are all copied into the index.
File names too get stored, but just for reference, not further access.


Ranking: two-stage ranking
---------------------------

With larger collections and more complex models there's inevitably a situation
when ranking *everything* using your best-quality model just is not fast enough.

One common solution to that is **two-stage ranking**, when at the first stage
you rank everything using a faster model, and at the second stage you rerank the
top-N results from the first stage using a slower model.

**Sphinx supports two-stage ranking with subselects** and certain guarantees on
`FACTORS()` behavior vs subselects and UDFs.

For the sake of example, assume that your queries can match up to 1 million
documents, and that you have a custom `SLOWRANK()` UDF that would be just too
heavy to compute 1 million times per query in reasonable time. Also assume that
reranking the top 3000 results obtained using even the simple default Sphinx
ranking formula with `SLOWRANK()` yields a negligible NDCG loss.

We can then **use a subselect** that uses a simple formula for the fast ranking
stage, and then reranks on `SLOWRANK()` in its outer sort condition, as follows.

```sql
SELECT * FROM (
  SELECT id, title, weight() fr, slowrank(factors()) sr
  FROM myindex WHERE match('hello')
  OPTION ranker=expr('sum(lcs)*10000+bm15')
  ORDER BY fr DESC LIMIT 3000
) ORDER BY sr DESC LIMIT 20
```

What happens here?

Even though `slowrank(factors())` is in the inner select, its evaluation can be
postponed until the *outer* reordering. And that does happen, because there are
the following 2 guarantees.

  1. `FACTORS()` blobs for the top inner documents are *guaranteed* to be
     available for the outer reordering.
  2. Inner UDF expressions that can be postponed until the outer stage are
     *guaranteed* to be postponed.

So during the inner select Sphinx still honestly matches 1,000,000 documents and
still computes the `FACTORS()` blobs and the ranking expression a million times.
But then it keeps just the top 3000 documents (and their signals), as requested
by the inner limit. Then it reranks just those documents, and calls `slowrank()`
just 3000 times. The it applies the final outer limit to returns the top-20 out
of the *reranked* documents. Voila.

Note how it's vital that you must **not** reference `sr` anywhere in the inner
query except the select list. Naturally, if you mention it in any inner `WHERE`
or `ORDER BY` or whatever other clause, Sphinx is **required** to compute that
during the inner select, can not postpone the heavy UDF evaluation anymore, and
the performance sinks.


Operations: "siege mode", temporary global query limits {#siege-mode}
----------------------------------------------------------------------

Sphinx `searchd` now has a so-called "siege mode" that temporarily imposes
server-wide limits on *all* the incoming `SELECT` queries, for a given amount
of time. This is useful when some client is flooding `searchd` with heavy
requests and, for whatever reason, stopping those requests at other levels
is complicated.

Siege mode is controlled via a few global server variables. The example just
below will introduce a siege mode for 15 seconds, and impose limits of at most
1000 processed documents and at most 0.3 seconds (wall clock) per query:
```sql
set global siege=15
set global siege_max_fetched_docs=1000
set global siege_max_query_msec=300
```

Once the timeout reaches zero, the siege mode will be automatically lifted.

There also are intentionally hardcoded limits you can't change, namely:

* upper limit for `siege` is 300 seconds, ie. 5 minutes
* upper limit for `siege_max_fetched_docs` is 1,000,000 documents
* upper limit for `siege_max_query_msec` is 1 second, ie. 1000 msec

Note that **current siege limits are reset when the siege stops.** So in the
example above, if you start another siege in 20 seconds, then that next siege
will be restarted with 1M docs and 1000 msec limits, and *not* the 1000 docs
and 300 msec limits from the previous one.

Siege mode can be turned off at any moment by zeroing out the timeout:
```sql
set global siege=0
```

The current siege duration left (if any) is reported in `SHOW STATUS`:
```sql
mysql> show status like 'siege%';
+------------------------+---------+
| Counter                | Value   |
+------------------------+---------+
| siege_sec_left         | 296     |
+------------------------+---------+
1 rows in set (0.00 sec)
```

And to check the current limits, you can check `SHOW VARIABLES`:
```sql
mysql> show variables like 'siege%';
+------------------------+---------+
| Counter                | Value   |
+------------------------+---------+
| siege_max_query_msec   | 1000    |
| siege_max_fetched_docs | 1000000 |
+------------------------+---------+
2 rows in set (0.00 sec)
```

Next order of business, the document limit has a couple interesting details
that require explanation.

First, the `fetched_docs` counter is calculated a bit differently for term and
non-term searches. For term searches, it counts all the (non-unique!) rows that
were fetched by full-text term readers, batch by batch. For non-term searches,
it counts all the (unique) alive rows that were matched (either by an attribute
index read, or by a full scan).

Second, for multi-index searches, the `siege_max_fetched_docs` limit will be
split across the local indexes (shards), weighted by their document count.

If you're really curious, let's discuss those bits in more detail.

The non-term search case is rather easy. All the actually stored rows (whether
coming either from a full scan or an attribute index reads) will be first
checked for liveness, then accounted in the `fetched_docs` counter, then either
further processed (with extra calculations, filters, etc). Bottom line, a query
limited this way will run "hard" calculations, filter checks, etc on at most
N rows. So best case scenario (if all `WHERE` filters pass), the query will
return N rows, and never even a single row more.

Now, the term search case is more interesting. The lowest-level term readers
will also emit individual rows, but as opposed to the "scan" case, either the
terms or the rows might be duplicated. The `fetched_docs` counter merely counts
those emitted rows, as it needs to limit the total amount of work done. So, for
example, with a 2-term query like `(foo bar)` the processing will stop when
*both* terms fetch N documents total from the full-text index... even if not
a single document was *matched* just yet! If a term is duplicated, for example,
like in a `(foo foo)` query, then *both* the occurrences will contribute to the
counter. Thus, for a query with M required terms all AND-ed together, the upper
limit on the *matched* documents should be roughly equal to N/M, because every
matched document will be counted as "processed" M times in every term reader.
So either `(foo bar)` or `(foo foo)` example queries with a limit of 1000 should
result in roughly 500 matches tops.

That "roughly" just above means that, occasionally, there might be slightly
more matches. As for performance reasons the term readers work in batches, the
actual `fetched_docs` counter might get slightly bigger than the imposed limit,
by the batch size at the most. But that must be insignificant as processing
just a single small batch is very quick.

And as for splitting the limit between the indexes, it's simply pro-rata,
based on the per-index document count. For example, assume that
`siege_max_fetched_docs` is set to 1000, and that you have 2 local indexes in
your query, one with 1400K docs and one with 600K docs respectively. (It does
not matter whether those are referenced directly or via a distributed index.)
Then the per-index limits will be set to 700 and 300 documents respectively.
Easy.

Last but not least, beware that the entire point of the "siege mode" is to
**intentionally degrade the search results for too complex searches**! Use with
extreme care; essentially only use it to stomp out cluster fires that can not
be quickly alleviated any other way; and at this point we recommend to only
*ever* use it manually.


Operations: network internals
------------------------------

Let's look into a few various `searchd` network implementation details that
might be useful from an operational standpoint: how it handles incoming client
queries, how it handles outgoing queries to other machines in the cluster, etc.

### Incoming (client) queries

#### Threading and networking modes

`searchd` currently supports two threading modes, `threads` and `thread_pool`,
and two networking modes are naturally tied to those threading modes.

In the first mode (`threads`), a separate dedicated per-client thread gets
spawned for every incoming network connection. It then handles everything, both
network IO and request processing. Having processing and network IO in the same
thread is optimal latency-wise, but unfortunately there are several other major
issues:

  * classic C10K problem: each inactive client stalls its thread, many inactive
    clients stall all available threads and DoS the server;
  * synchronous processing problem: thread that works on a request can't react
    to *any* network events such as client going away;
  * slow client problem: active but slow client stalls its thread while doing
    either network request reads or response writes.

In the second mode (`thread_pool`), worker threads are isolated from client IO,
and only work on the requests. All client network IO is performed in a dedicated
network thread. It runs the so-called **net loop** that multiplexes (many) open
connections and handles them (very) efficiently.

What does the network thread actually do? It does all network reads and writes,
for all the protocols (SphinxAPI, SphinxQL, HTTP) too, by the way. It also does
a tiny bit of its own packet processing (basically parsing just a few required
headers). For full packet parsing and request processing, it sends the request
packets to worker threads from the pool, and gets the response packets back.

You can create more than 1 network thread using the `net_workers` directive.
That helps when the query pressure is so extreme that 1 thread gets maxed out.
On a quick and dirty benchmark with v.3.4 (default `searchd` settings; 96-core
server; 128 clients doing point selects), we got ~110K RPS with 1 thread. Using
2 threads (ie. `net_workers = 2`) improved that to ~140K RPS, 3 threads got us
~170K RPS, 4 threads got ~180K-190K RPS, and then 5 and 6 threads did not yield
any further improvements.

Having a dedicated network thread (with some `epoll(7)` magic of course) solves
all the aforementioned problems. 10K (and more) open connections with reasonable
total RPS are now easily handled even with 1 thread, instead of forever blocking
10K OS threads. Ditto for slow clients, also nicely handled by just 1 thread.
And last but not least, it asynchronously watches all the sockets even while
worker threads process the requests, and signals the workers as needed. Nice!

Of course all those solutions come at a price: there is a rather inevitable
**tiny latency impact**, caused by packet data traveling between network and
worker threads. On our benchmarks with v.3.4 we observe anywhere between 0.0 and
0.4 msec average extra latency per query, depending on specific benchmark setup.
Now, given that *average* full-text queries usually take 20-100 msec and more,
in most cases this extra latency impact would be under 2%, if not negligible.

Still, take note that in a *borderline* case when your *average* latency is at
~1 msec range, ie. when practically *all* your queries are quick and tiny, even
those 0.4 msec might matter. Our point select benchmark is exactly like that,
and `threads` mode very expectedly shines! At 128 clients we get ~180 Krps in
`thread_pool` mode and ~420 Krps in `threads` mode. The respective average
latencies are 0.304 msec and 0.711 msec, the difference is 0.407 msec,
everything computes.

Now, *client* application approaches to networking are also different:

  * one-off connections, ie. new one established for every query;
  * small pool, ie. say up to 100-200 "active enough" connections;
  * huge pool, ie. 1K..10K+ "lazy enough" connections (aka C10K).

**Net loop mode handles all these cases gracefully** when properly configured,
even under suddenly high load. As the workers threads count is limited, incoming
requests that we do not have the capacity to process are simply going to be
enqueued and and wait for a free worker thread.

**Client thread mode does not**. When the `max_children` thread limit is too
small, any connections over the limit are rejected. Even if threads currently
using up that limit are sitting doing nothing! And when the limit is too high,
`searchd` is at risk, `threads` could fail *miserably* and kill the server.
Because if we allow "just" 1000 expectedly lazy clients, then we have to raise
`max_children` to 1000, but then nothing prevents the clients from becoming
active and firing a volley of *simultaneous* heavy queries. Instantly converting
1000 mostly sleeping threads to 1000 very active ones. Boom, your server is dead
now, `ssh` does not work, where was that bloody KVM password?

With net loop, defending the castle is (much) easier. Even 1 network thread can
handle network IO for 1000 lazy clients alright. So we can keep `max_children`
reasonable, properly based on the server core count, *not* the expected open
connections count. Of course, a sudden volley of 1000 simultaneous heavy queries
will never go completely unnoticed. It will still max out the worker threads.
For the sake of example, say we set our limit at 40 threads. Those 40 threads
will get instantly busy processing 40 requests, but 960 more requests will be
merely enqueued rather than using up 960 more threads. In fact, queue length can
also be limited by `queue_max_length` directive, but the default value is 0
(unlimited). Boom, your server is now quite busy, and the request queue length
might be massive. But at least `ssh` works, and just 40 cores are busy, and
there are might be a few spare ones. Much better.

Quick summary?

`thread_pool` threading and net loop networking are better in most of the
production scenarios, and hence they are the default mode. Yes, sometimes they
*might* add tiny extra latency, but then again, sometimes they would not.

However, in one very special case (when all your queries are sub-millisecond
and you are actually gunning for 500K+ RPS), consider using `threads` mode,
because less overheads and better RPS.

#### Client disconnects

Clients can suddenly disconnect for any reason, at any time. Including while the
server is busy processing a heavy read request. Which the server could then
cancel, and save itself some CPU and disk.

In client thread mode, we can not do anything about that disconnect, though.
Basically, because while the per-client thread is busy processing the request,
it can not afford to constantly check the client socket.

In net loop mode, yes we can! Net loop constantly watches *all* the client
sockets using a dedicated thread, catches such disconnects ASAP, and then either
automatically raises the early termination flag if there is a respective worker
thread (exactly as manual [`KILL` statement](#kill-syntax) would), or removes
the previously enqueued request if it was still waiting for a worker.

Therefore, **in net loop mode, client disconnect auto-KILLs its current query**.
Which might sounds dangerous but really is not. Basically because the affected
queries are reads.

### Outgoing (distributed) queries

Queries that involve remote instances generally work as follows:

  1. `searchd` connects to all the required remote `searchd` instances (we call
     them "agents",) and sends the respective queries to those instances.
  2. Then it runs all the required local queries, if any.
  3. Then it waits for the remote responses, and does query retries as needed.
  4. Then it aggregates the final result set, and serves that back to client.

Generally quite simple, but of course there are quite a few under-the-hood
implementation details and quirks. Let's cover the bigger ones.

The inter-instance protocol is SphinxAPI, so all instances in the cluster *must*
have a SphinxAPI listener.

By default, a new connection to every agent is created for every query. However,
in `workers = threads` mode we additionally support `agent_persistent` and
`persistent_connections_limit` directives that tell the master instance to keep
and reuse a pool of open persistent connections to every such agent. The limit
is per-agent.

Connection step timeout is controlled by `agent_connect_timeout` directive, and
defaults to 1000 msec (1 sec). Also, searches (`SELECT` queries) might retry on
connection failures, up to `agent_retry_count` times (default is 0 though), and
they will sleep for `agent_retry_delay` msec on each retry.

Note that if network connections attempts to some agent stall and timeout
(rather than failing quickly), you can end up with *all* distributed queries
also stalling for at least 1 sec. The root cause here is usually more of a host
configuration issue; say, a firewall dropping packets. Still, it makes sense to
lower the `agent_connect_timeout` preemptively, to reduce the overall latency
even in the unfortunate event of such configuration issues suddenly popping up.
We find that timeouts from 100 to 300 msec work well within a single DC.

Querying step timeout is in turn controlled by `agent_query_timeout`, and
defaults to 3000 msec, or 3 sec. Same retrying rules apply. Except that query
timeouts are usually caused by slow queries rather than network issues! Meaning
that the default `agent_query_timeout` should be adjusted with quite more care,
taking into account your typical queries, SLAs, etc.

Note that these timeouts can (and sometimes must!) be overridden by the client
application on a per-query basis. For instance, what if 99% of the time we run
quick searches that must complete say within 0.5 sec according to our SLA, but
occasionally we still need to fire an analytical search query taking much more,
say up to 1 minute? One solution here would be to set `searchd` defaults at
`agent_query_timeout = 500` for the majority of the queries, and specify
`OPTION agent_query_timeout = 60000` in the individual special queries.

`agent_retry_count` applies to *both* connection and querying attempts. Example,
`agent_retry_count = 1` means that either connection *or* query attempt would be
retried, but not both. More verbosely, if `connect()` failed initially, but then
succeeded on retry, and then the query timed out, then the query does *not* get
retried because we were only allowed 1 retry total and we spent it connecting.


Operations: dumping data
-------------------------

Version 3.5 adds very initial `mysqldump` support to `searchd`. SphinxQL dialect
differences and schema quirks currently dictate that you must:

  1. Use `-c` (aka `--complete-insert`) option.
  2. Use `--skip-opt` option (or `--skip-lock-tables --add-locks=off`).
  3. Use `--where` to adjust `LIMIT` at the very least.

For example:
```bash
mysqldump -P 9306 -c --skip-opt dummydb test1 --where "id!=0 limit 100"
```

A few more things will be rough with this initial implementation:

  * Stored fields are not included just yet.
  * Some of the queries `mysqldump` tries are expected to fail.
    Non-fatally if you're lucky enough.
  * Huge result sets are expected to fail, on `searchd` side.

Anyway, it's a start.


Operations: binlogs
--------------------

Binlogs are our write-ahead logs, or WALs. They ensure data safety on crashes,
OOM kills, etc.

You can tweak their behavior using the following directives:

  * [`binlog`](#binlog-directive) to enable or disable binlogs in datadir mode;
  * [`binlog_flush_mode`](#binlog_flush_mode-directive) to tweak the flushing;
  * [`binlog_max_log_size`](#binlog_max_log_size-directive) to tweak the single
    log file size threshold.

In legacy non-datadir mode there's the
[`binlog_path`](sphinx2.html#conf-binlog-path) directive instead of `binlog`.
It lets you either disable binlogs, or change their storage location.

**WE STRONGLY RECOMMEND AGAINST DISABLING BINLOGS.** That puts *any* writes to
Sphinx indexes at constant risk of data loss.

The current defaults are as follows.

  * `binlog = 1`, binlogs are enabled
  * `binlog_flush_mode = 2`, fflush and fsync every 1 sec
  * `binlog_max_log_size = 128M`, open a new log file every 128 mb

Binlogs are per-index. The settings above apply to all indexes (and their
respective binlogs) at once.

All the binlogs files are stored in the `$datadir/binlogs/` folder in the
datadir mode, or in `binlog_path` (which defaults to `.`) in the legacy mode.

Binlogs are automatically replayed after any unclean shutdown. Replay should
recover any freshly written index data that was already stored in binlogs, but
not yet stored in the index disk files.

Single-index binlog replay is single-threaded. However, multi-index replay is
multi-threaded. It uses a small thread pool, sized at 2 to 8 threads, depending
on how many indexes there are. The upper limit of 8 is a hardcoded limit that
worked well on our testing.


Operations: query logs
-----------------------

By default, `searchd` keeps a query log file, with erroneous and/or slow queries
logged for later analysis. The default slow query threshold is 1 sec. The output
format is valid SphinxQL, and the required query metainfo (timestamps, execution
timings, error messages, etc) is *always* formatted as a comment. So that logged
queries could be easily repeated for testing purposes.

To disable the query log completely, set `query_log = no` in your config file.

> Note that in legacy non-datadir mode this behavior was pretty much inverted:
`query_log` defaulted to an empty path, so disabled by default; and log format
defaulted to the legacy "plain" text format (that was only able to log searches,
not query errors, nor other query types); and the slow query threshold defaulted
to zero, which causes problems under load (see below). Meh. We strongly suggest
switching to datadir mode, anyway.

Erroneous queries are logged along with the specific error message. Both query
syntax errors (for example, "unexpected IDENT" on a `selcet 1` typo) and server
errors (such as the dreaded "maxed out") get logged.

Slow queries are logged along with the elapsed wall time at the very least, and
other metainfo such as agent timings where available.

Slow query threshold is set by the `query_log_min_msec` directive. The allowed
range is from 0 to 3600000 (1 hour in msec), and the default is 1000 (1 sec).

`SET GLOBAL query_log_min_msec = <new_value>` changes the threshold on the fly,
but beware that the *config* value will be used again after `searchd` restart.

Logged SphinxQL statements currently include `SELECT`, `INSERT`, and `REPLACE`;
this list will likely grow in the future.

Slow searches are logged over *any* protocol, ie. slow SphinxAPI (and HTTP)
queries get logged too. They are formatted as equivalent SphinxQL SELECTs.

Technically, you can set `query_log_min_msec` threshold to 0 and make `searchd`
log all queries, but almost always that would be a mistake. After all, this log
is designed for errors and slow queries, which are comparatively infrequent.
While attempting to "always log everything" this way might be okay on a small
scale, it *will* break under heavier loads: it *will* affect performance at some
point, it risks overflowing the disk, etc. And it doesn't log "everything"
anyway, as the list of statements "eligible" for query log is limited.

To capture everything, you should use a different mechanism that `searchd` has:
the raw SphinxQL logger, aka [`sql_log_file`](#sql_log_file-variable). Now, that
one is designed to handle extreme loads, it works really fast, and it guarantees
to capture pretty much *everything* at all. Even the queries that crash the SQL
parser should get caught, because the raw logger triggers right after the socket
reads! However, exhausting the free disk space is still a risk.


Operations: user auth
---------------------

Sphinx v.3.6 adds basic MySQL user auth for SphinxQL. Here's the gist.

The key directive is `auth_users`, and it takes a CSV file name, so for example
`auth_users = users.csv` in the full form. Note that in datadir mode the users
file must reside in the VFS, ie. in `$datadir/extra` (or any subfolders).

There must be 3 columns named `user`, `auth`, and `flags`, and a header line
must explicitly list them, as follows.

```bash
$ cat users.csv
user, auth, flags
root, a94a8fe5ccb19ba61c4c0873d391e987982fbbd3
```

The `user` column must contain the user name. The names are case-insensitive,
and get forcibly lowercased.

The `auth` column must either be empty (meaning "no password"), or contain the
SHA1 password hash. This is dictated by MySQL protocol. Because we piggyback on
its `mysql_native_password` auth method based on SHA1 hashes.

You can generate the hash as follows. Mind the gap: the `-n` switch is essential
here, or the line feed also gets hashed, and you get a very different hash.

```bash
$ echo -n "test" | sha1sum
a94a8fe5ccb19ba61c4c0873d391e987982fbbd3  -
```

The `flags` column is reserved for future use, and is optional.

Invalid lines are reported and skipped. At least one valid line is required.

For security reasons, `searchd` will **NOT** start if `auth_users` file fails
to load, or does not have *any* valid user entries at all. This is intentional.
We believe that once you explicitly enable and require auth, you do **not** want
the server automatically reverting to "no auth" mode because of config typos,
bad permissions, etc.

### SHA1 security notes

Let's briefly discuss "broken" SHA1 hashes, how Sphinx uses them, and what are
the possible attack vectors here.

**Sphinx never stores plain text passwords.** So grabbing the *passwords*
themselves is not possible.

**Sphinx stores SHA1 hashes of the passwords.** And if an attacker gains access
to those, they can:

  - access any other Sphinx or MySQL instances that use that hash; or
  - attempt to reverse the hash for the password (which may or may not succeed).

Therefore, **SHA1 hashes must be secured just as well as plain text passwords**.

Now, a bit of good news, even though hash leak means access leak, the original
password *text* itself is not *necessarily* at risk.

SHA1 is considered "broken" since 2020 but that only applies to the so-called
collision attacks, basically affecting the digital signatures. The feasibility
of recovering the password does still depend on its quality. That includes any
previous leaks.

For instance, bruteforcing SHA1 for *all* mixed 9-char letter-digit passwords
should only take 3 days on a single Nvidia RTX 4090 GPU. But make that a good,
strong, truly random 12-char mix and we're looking at 2000 GPU-years. But leak
that password just once, and eventually attackers only needs seconds.

Bottom line here? **Use strong random passwords, and never reuse them.**

Next item, **traffic sniffing is actually at the same ballpark as a hash leak**,
security-wise. Sniffing a successfully authed session provides enough data to
attempt bruteforcing your passwords! Strong passwords will hold, weak ones will
break. This isn't even Sphinx specific and applies to MySQL just as well.

Last but not least, **why implement old SHA1 in 2023?** Because MySQL protocol.
We naturally *have* to use its auth methods too. And we wanna be as compatible
with various clients (including older ones) as possible. And that's a priority,
especially given that Sphinx must be normally used within a secure perimeter
anyway.

So despite that MySQL server defaults to `caching_sha2_password` auth method
these days, the most **compatible** auth method that **clients** support still
would be `mysql_native_password` based on SHA1.

For the record, while it's feasible to add `caching_sha2_password` support too,
that is *not* currently on the roadmap. We will revisit this when (or if) newer
*clients* start dropping `mysql_native_password` support altogether and break;
or if SHA1 breaks to a point when storing SHA1(password) becomes unacceptable;
or if anyone on the team gets bored enough that an additional auth method seems
like a good "evening fun" side project. (The latter being the most likely
scenario, if you ask me.)


SphinxQL reference
-------------------

This section should eventually contain the complete SphinxQL reference.

If the statement you're looking for is not yet documented here, please refer to
the legacy [Sphinx v.2.x reference](sphinx2.html#sphinxql-reference). Beware
that the legacy reference may not be up to date.

Here's a complete list of SphinxQL statements.

  * [ALTER syntax](#alter-syntax)
  * [ALTER OPTION syntax](#alter-option-syntax)
  * [ATTACH INDEX syntax](sphinx2.html#sphinxql-attach-index)
  * [BEGIN syntax](sphinx2.html#sphinxql-begin)
  * [BULK UPDATE syntax](#bulk-update-syntax)
  * [CALL syntax](#call-syntax)
  * [CALL KEYWORDS syntax](sphinx2.html#sphinxql-call-keywords)
  * [CALL QSUGGEST syntax](sphinx2.html#sphinxql-call-qsuggest)
  * [CALL SNIPPETS syntax](sphinx2.html#sphinxql-call-snippets)
  * [CALL SUGGEST syntax](sphinx2.html#sphinxql-call-suggest)
  * [COMMIT syntax](sphinx2.html#sphinxql-commit)
  * [CREATE FUNCTION syntax](sphinx2.html#sphinxql-create-function)
  * [CREATE INDEX syntax](#create-index-syntax)
  * [CREATE PLUGIN syntax](sphinx2.html#sphinxql-create-plugin)
  * [CREATE TABLE syntax](#create-table-syntax)
  * [DELETE syntax](sphinx2.html#sphinxql-delete)
  * [DESCRIBE syntax](sphinx2.html#sphinxql-describe)
  * [DROP FUNCTION syntax](sphinx2.html#sphinxql-drop-function)
  * [DROP INDEX syntax](#drop-index-syntax)
  * [DROP PLUGIN syntax](sphinx2.html#sphinxql-drop-plugin)
  * [DROP TABLE syntax](#drop-table-syntax)
  * [EXPLAIN SELECT syntax](#explain-select-syntax)
  * [FLUSH ATTRIBUTES syntax](sphinx2.html#sphinxql-flush-attributes)
  * [FLUSH INDEX syntax](#flush-index-syntax)
  * [FLUSH HOSTNAMES syntax](sphinx2.html#sphinxql-flush-hostnames)
  * [FLUSH RAMCHUNK syntax](sphinx2.html#sphinxql-flush-ramchunk)
  * [INSERT syntax](#insert-syntax)
  * [KILL syntax](#kill-syntax)
  * [OPTIMIZE INDEX syntax](sphinx2.html#sphinxql-optimize-index)
  * [RELOAD INDEX syntax](sphinx2.html#sphinxql-reload-index)
  * [RELOAD PLUGINS syntax](sphinx2.html#sphinxql-reload-plugins)
  * [REPLACE syntax](#replace-syntax)
  * [ROLLBACK syntax](sphinx2.html#sphinxql-rollback)
  * [SELECT syntax](#select-syntax)
  * [SELECT expr syntax](#select-expr-syntax)
  * [SELECT @uservar syntax](#select-uservar-syntax)
  * [SELECT @@sysvar syntax](#select-sysvar-syntax)
  * [SET syntax](sphinx2.html#sphinxql-set)
  * [SET TRANSACTION syntax](sphinx2.html#sphinxql-set-transaction)
  * [SHOW AGENT STATUS syntax](sphinx2.html#sphinxql-show-agent-status)
  * [SHOW CHARACTER SET syntax](sphinx2.html#sphinxql-show-character-set)
  * [SHOW COLLATION syntax](sphinx2.html#sphinxql-show-collation)
  * [SHOW CREATE TABLE syntax](#show-create-table-syntax)
  * [SHOW DATABASES syntax](sphinx2.html#sphinxql-show-databases)
  * [SHOW INDEX AGENT STATUS syntax](#show-index-agent-status-syntax)
  * [SHOW INDEX FROM syntax](#show-index-from-syntax)
  * [SHOW INDEX SETTINGS syntax](sphinx2.html#sphinxql-show-index-settings)
  * [SHOW INDEX STATUS syntax](sphinx2.html#sphinxql-show-index-status)
  * [SHOW META syntax](sphinx2.html#sphinxql-show-meta)
  * [SHOW OPTIMIZE STATUS syntax](#show-optimize-status-syntax)
  * [SHOW PLAN syntax](sphinx2.html#sphinxql-show-plan)
  * [SHOW PLUGINS syntax](sphinx2.html#sphinxql-show-plugins)
  * [SHOW PROFILE syntax](#show-profile-syntax)
  * [SHOW STATUS syntax](#show-status-syntax)
  * [SHOW TABLES syntax](sphinx2.html#sphinxql-show-tables)
  * [SHOW THREADS syntax](#show-threads-syntax)
  * [SHOW VARIABLES syntax](#show-variables-syntax)
  * [SHOW WARNINGS syntax](sphinx2.html#sphinxql-show-warnings)
  * [TRUNCATE RTINDEX syntax](sphinx2.html#sphinxql-truncate-rtindex)
  * [UPDATE syntax](#update-syntax)


### ALTER syntax

```sql
ALTER TABLE <ftindex> {ADD | DROP} COLUMN <colname> <coltype>
```

The `ALTER` statement lets you add or remove columns from existing full-text
indexes on the fly. It only supports local indexes, not distributed.

As of v.3.6, most of the column types are supported, except arrays.

Beware that `ALTER` exclusively locks the index for its entire duration. Any
concurrent writes *and* reads will stall. That might be an operational issue for
larger indexes. However, given that `ALTER` affects attributes only, and given
that attributes are expected to fit in RAM, that is frequently okay anyway.

You can expect `ALTER` to complete in approximately the time needed to read and
write the attribute data once, and you can estimate that with a simple `cp` run
on the respective data files.

Newly added columns are initialized with default values, so 0 for numerics,
empty for strings and JSON, etc.

Here are a few examples.

```sql
mysql> ALTER TABLE plain ADD COLUMN test_col UINT;
Query OK, 0 rows affected (0.04 sec)

mysql> DESC plain;
+----------+--------+
| Field    | Type   |
+----------+--------+
| id       | bigint |
| text     | field  |
| group_id | uint   |
| ts_added | uint   |
| test_col | uint   |
+----------+--------+
5 rows in set (0.00 sec)

mysql> ALTER TABLE plain DROP COLUMN group_id;
Query OK, 0 rows affected (0.01 sec)

mysql> DESC plain;
+----------+--------+
| Field    | Type   |
+----------+--------+
| id       | bigint |
| text     | field  |
| ts_added | uint   |
| test_col | uint   |
+----------+--------+
4 rows in set (0.00 sec)
```


### ALTER OPTION syntax

```sql
ALTER TABLE <ftindex> SET OPTION <name> = <value>
```

The `ALTER ... SET OPTION ...` statement lets you modify certain index settings
on the fly.

At the moment, the only supported option is `pq_max_rows` in PQ indexes.


### BULK UPDATE syntax

```sql
BULK UPDATE [INPLACE] ftindex (id, col1 [, col2 [, col3 ...]]) VALUES
(id1, val1_1 [, val1_2 [, val1_3 ...]]),
(id2, val2_1 [, val2_2 [, val2_3 ...]]),
...
(idN, valN_1 [, valN_2 [, valN_3 ...]])
```

`BULK UPDATE` lets you update multiple rows with a single statement. Compared
to running N individual statements, bulk updates provide both cleaner syntax and
better performance.

Overall they are quite similar to regular updates. To summarize quickly:

  * you can update (entire) attributes, naturally keeping their types (even
    when changing the width, ie. when updating a string, or entire JSON, etc);
  * you can update numeric values within JSON, also keeping their types (and
    naturally keeping the width).

First column in the list must always be the `id` column. Rows are uniquely
identified by document ids.

Other columns to update can either be regular attributes, or individual JSON
keys, also just as with regular `UPDATE` queries. Here are a couple examples:

```sql
BULK UPDATE test1 (id, price) VALUES (1, 100.00), (2, 123.45), (3, 299.99)
BULK UPDATE test2 (id, json.price) VALUES (1, 100.00), (2, 123.45), (3, 299.99)
```

All the value types that the regular `UPDATE` supports (ie. numerics, strings,
JSON, etc) are also supported by the bulk updates.

The `INPLACE` variant behavior matches the regular `UPDATE INPLACE` behavior,
and ensures that the updates are either performed in-place, or fail.

**Bulk updates of existing values *must* keep the type.** This is a natural
restriction for regular attributes, but it also applies to JSON values. For
example, if you update an integer JSON value with a float, then that float will
get converted (truncated) to the current integer type.

Compatible value type conversions will happen. Truncations are allowed.

Incompatible conversions will fail. For example, strings will *not* be
auto-converted to numeric values.

Attempts to update non-existent JSON keys will fail.

**Bulk updates may only apply partially, and then fail. They are NOT atomic.**
For simplicity and performance reasons, they process rows one by one, they may
fail mid-flight, and there will be no rollback in that case.

For example, if you're doing an in-place bulk update over 10 rows, that may
update the first 3 rows alright, then fail on the 4-th row because of, say,
an incompatible JSON type. The remaining 6 rows will not be updated further,
even if they actually *could* be updated. But neither will the 3 successful
updates be rolled back. One should treat the entire bulk update as failed
in these cases anyway.


### CALL syntax

```sql
CALL <built_in_proc>([<arg> [, <arg [, ...]]])
```

`CALL` statement lets you call a few special built-in "procedures" that expose
various additional tools. The specific tools and their specific arguments vary,
and you should refer to the respective `CALL_xxx` section for that. This section
only discusses a few common syntax things.

The reasons for even having a separate `CALL` statement rather than exposing
those tools as functions accessible using the `SELECT expr` statement were:

  - **Limited scope.** Regular function expressions are available in *any*
    `SELECT`, not just the row-less expr-form. However, some (or even all)
    CALL-able procedures do not support being called in a per-row context.
  - **Return type.** Functions can't return an arbitrary table, procedures can.
  - **Named arguments.** Functions only support positional arguments, procedures
    can have named ones. (Though frankly, these days this can be alleviated with
    a map-typed function argument, too.)

Those reasons actually summarize most of the rest of this section, too!

**Procedures and functions are very different things.** They don't mingle much.
Functions (such as `SIN()` etc) are something that you can meaningfully compute
in your `SELECT` for every single row. Procedures (like `CALL KEYWORDS`) usually
are something that makes little since in the per-row context, something that you
are supposed to invoke individually.

**Procedure `CALL` will generally return an arbitrary table.** The specific
columns and rows depend on the specific procedure.

**Procedures can have named arguments.** A few first arguments would usually
still be positional, for example, 1st argument must always be an index name (for
a certain procedure), etc. But then starting from a certain position you would
specify the "name-value" argument pairs using the SQL style `value AS name`
syntax, like this:

```sql
CALL FOO('myindex', 0 AS strict, 1 AS verbose)
```

**There only are built-in procedures.** We do not plan to implement PL/SQL.

From here, refer to the respective "CALL xxx syntax" documentation sections for
the specific per-procedure details.


### CREATE INDEX syntax

```sql
CREATE INDEX [<name>] ON <ftindex>({<col_name> | <json_field>
  | {UINT | BIGINT | FLOAT}(<json_field>))
```

`CREATE INDEX` statement lets you create attribute indexes (aka secondary
indexes) either over regular columns, or JSON fields.

Attribute indexes are identified and managed by names. Names must be unique.
You can use either `DESCRIBE` or [`SHOW INDEX FROM`](#show-index-from-syntax)
statements to examine what indexes (and names) already exist.

If an explicit attribute index name is not specified, `CREATE INDEX` will
generate one automatically from the indexed value expression. Names generated
from JSON expressions are simplified for brevity, and might conflict, even with
other autogenerated names. In that case, just use the full syntax, and provide
a different attribute index name explicitly.

Up to 64 attribute indexes per (full-text) index are allowed.

Currently supported indexable value types are numeric types and integer sets
(aka MVA), ie. `UINT`, `BIGINT`, `FLOAT`, `MULTI`, and `MULTI64` in SphinxQL
terms. Indexing strings is not yet supported.

Indexing both regular columns and JSON fields is pretty straightforward, for
example:

```sql
CREATE INDEX idx_price ON products(price)
CREATE INDEX idx_tags ON products(tags_mva)
CREATE INDEX idx_foo ON product(json.foo)
CREATE INDEX idx_bar ON product(json.qux[0].bar)
```

JSON fields are not typed statically, but attributes indexes are, so we *must*
cast JSON field values when indexing. Currently supported casts are `UINT`,
`BIGINT`, and `FLOAT` only. Casting from JSON field to integer set is not yet
supported. When the explicit type is missing, casting defaults to `UINT`, and
produces a warning:

```sql
mysql> CREATE INDEX idx_foo ON rt1(j.foo);
Query OK, 0 rows affected, 1 warning (0.08 sec)

mysql> show warnings;
+---------+------+------------------------------------------------------------------------------+
| Level   | Code | Message                                                                      |
+---------+------+------------------------------------------------------------------------------+
| warning | 1000 | index 'rt1': json field type not specified for 'j.foo'; defaulting to 'UINT' |
+---------+------+------------------------------------------------------------------------------+
1 row in set (0.00 sec)

mysql> DROP INDEX idx_foo ON t1;
Query OK, 0 rows affected (0.00 sec)

mysql> CREATE INDEX idx_foo ON t1(FLOAT(j.foo));
Query OK, 0 rows affected (0.09 sec)
```

Note that `CREATE INDEX` locks the target full-text index exclusively, and
larger indexes may take a while to create.


### CREATE TABLE syntax

```sql
CREATE TABLE <name> (id BIGINT, <field> [, <field> ...] [, <attr> ...])
[OPTION <opt_name> = <opt_value> [, <opt_name> = <opt_value [ ... ]]]

<field> := <field_name> {FIELD | FIELD_STRING}
<attr> := <attr_name> <attr_type>
```

`CREATE TABLE` lets you dynamically create a new RT full-text index. It requires
datadir mode to work.

The specified column order must follow the "id/fields/attrs" rule, as discussed
in the ["Using index schemas"](#using-index-schemas) section. Also, there *must*
be at least 1 field defined. The attributes are optional. Here's an example.

```sql
CREATE TABLE dyntest (id BIGINT, title FIELD_STRING, content FIELD,
  price BIGINT, lat FLOAT, lon FLOAT, vec1 INT8[3])
```

All column types should be supported. The complete type names list is available
in the ["Attributes"](#attributes) section.

Array types are also supported now. Their dimensions must be given along with
the element type, see example above. `INT[N]`, `INT8[N]`, and `FLOAT[N]` types
are all good.

Most of the [index configuration directives](#index-config-reference) available
in the config file can now also be specified as options to `CREATE TABLE`, just
as follows.

```sql
CREATE TABLE test2 (id BIGINT, title FIELD)
OPTION rt_mem_limit=256M, min_prefix_len=3, charset_table='english, 0..9'
```

Directives that aren't supported in the `OPTION` clause are:

  * schema definition ones, ie. `attr_xxx` etc (use `CREATE TABLE` instead)
  * distributed index ones (dynamic distributed indexes not supported yet)
  * `path`, `source`, `type` (not applicable to dynamic RT indexes)
  * `create_index` (use `CREATE INDEX` instead)
  * `mlock`, `ondisk_attrs`, `preopen`, `regexp_filter` (maybe sometime)

Note that repeated `OPTION` entries are silently ignored, and only the first
entry takes effect. So to specify multiple files for `stopwords`, `mappings`, or
`morphdict`, just list them all in a single `OPTION` entry.

```sql
CREATE TABLE test2 (id BIGINT, title FIELD)
OPTION stopwords='stops1.txt stops2.txt stops3.txt'
```


### DROP INDEX syntax

```sql
DROP INDEX <name> ON <ftindex>
```

`DROP INDEX` statement lets you remove no longer needed attribute index from
a given full-text index.

Note that `DROP INDEX` locks the target full-text index exclusively. Usually
dropping an index should complete pretty quickly (say a few seconds), but your
mileage may vary.


### DROP TABLE syntax

```sql
DROP TABLE [IF EXISTS] <ftindex>
```

`DROP TABLE` drops a previously created full-text index. It requires datadir
mode to work.

The optional `IF EXISTS` clause makes `DROP` succeed even the target index does
not exist. Otherwise, it fails.


### EXPLAIN SELECT syntax

```sql
EXPLAIN SELECT ...
```

`EXPLAIN` prepended to (any) legal `SELECT` query collects and display the query
plan details: what indexes could be used at all, what indexes were chosen, etc.

The actual query does *not* get executed, only the planning phase, and therefore
any `EXPLAIN` must return rather quickly.


### FLUSH INDEX syntax

```sql
FLUSH INDEX <index>
```

`FLUSH INDEX` forcibly syncs the given index from RAM to disk. On success, all
index RAM data gets written (synced) to disk. Either an RT or PQ index argument
is required.

Running this sync does *not* evict any RAM-based data from RAM. All that data
stays resident and, actually, completely unaffected. It's only the on-disk copy
of the data that gets synced with the most current RAM state. This is the very
same sync-to-disk operation that gets internally called on clean shutdown and
periodic flushes (controlled by `rt_flush_period` setting).

So an explicit `FLUSH INDEX` speeds up crash recovery. Because `searchd` only
needs to replay WAL (binlog) operations logged since last good sync. That makes
it useful for quick-n-dirty backups. (Or, when you can pause writes, make that
quick-n-clean ones.) Because index backups made immediately after an explicit
`FLUSH INDEX` can be used without any WAL replay delays.

This statement was previously called `FLUSH RTINDEX`, and that now-legacy syntax
will be supported as an alias for a bit more time.  


### INSERT syntax

```sql
INSERT INTO <ftindex> [(<column>, ...)]
VALUES (<value>, ...) [, (...)]
```

`INSERT` statement inserts new, not-yet-existing rows (documents) into a given
RT index. Attempts to insert an already existing row (as identified by id) must
fail.

There's also the `REPLACE` statement (aka "upsert") that, basically, won't fail
and will always insert the new data. See [`REPLACE` docs] for details.

Here go a few simple examples, with and without the explicit column list.

```sql
# implicit column list example
# assuming that the index has (id, title, content, userid)
INSERT INTO test1 VALUES (123, 'hello world', 'some content', 456);

# explicit column list
INSERT INTO test1 (id, userid, title) VALUES (234, 456, 'another world');
```

The list of columns is optional. You can omit it and rely on the schema order,
which is "id first, fields next, attributes last". For a bit more details, see
the ["Schemas: query order"](#schemas-query-order) section.

When specified, the list of columns **must** contain the `id` column. Because
that is how Sphinx identifies the documents. Otherwise, inserts will fail.

Any other columns *can* be omitted from the explicit list. They are then filled
with the respective default values for their type (zeroes, empty strings, etc).
So in the example just above, `content` field will be empty for document 234
(and if we omit `userid`, it will be 0, and so on).

Expressions are **not** yet supported, all values must be provided explicitly,
so `INSERT ... VALUES (100 + 23, 'hello world')` is **not** legal.

Last but not least, `INSERT` can insert multiple rows at a time if you specify
multiple lists of values, as follows.

```sql
# multi-row insert example
INSERT INTO test1 (id, title) VALUES
  (1, 'test one'),
  (2, 'test two'),
  (3, 'test three')
```


### KILL syntax

```sql
KILL <thread_id>
KILL SLOW <min_msec> MSEC
```

`KILL` lets you forcibly terminate long-running statements based either on
thread ID, or on their current running time.

For the first version, you can obtain the thread IDs using the
[`SHOW THREADS`](#show-threads-syntax) statement.

Note that forcibly killed queries are going to return almost as if they
completed OK rather than raise an error. They will return a partial result set
accumulated so far, and raise a "query was killed" warning. For example:

```sql
mysql> SELECT * FROM rt LIMIT 3;
+------+------+
| id   | gid  |
+------+------+
|   27 |  123 |
|   28 |  123 |
|   29 |  123 |
+------+------+
3 rows in set, 1 warning (0.54 sec)

mysql> SHOW WARNINGS;
+---------+------+------------------+
| Level   | Code | Message          |
+---------+------+------------------+
| warning | 1000 | query was killed |
+---------+------+------------------+
1 row in set (0.00 sec)
```

The respective network connections are not going to be forcibly closed.

At the moment, the only statements that can be killed are `SELECT`, `UPDATE`,
and `DELETE`. Additional statement types might begin to support `KILL` in the
future.

In both versions, `KILL` returns the number of threads marked for termination
via the affected rows count:

```sql
mysql> KILL SLOW 2500 MSEC;
Query OK, 3 row affected (0.00 sec)
```

Threads already marked will not be marked again and reported this way.

There are no limits on the `<min_msec>` parameter for the second version, and
therefore, `KILL SLOW 0 MSEC` is perfectly legal syntax. That specific statement
is going to kill *all* the currently running queries. So please use with a pinch
of care.


### REPLACE syntax

```sql
REPLACE INTO <ftindex> [(<column>, ...)]
VALUES (<value>, ...) [, (...)]
[KEEP (<column> [, ...])]
```

`REPLACE` is similar to `INSERT`, so for the common background you should also
refer to the [INSERT syntax](#insert-syntax) section). But there are two quite
important differences.

First, it never raises an error on existing rows (aka ids). It basically should
always succeed, one way or another: by either "just" inserting the new row, or
by overwriting (aka replacing!) the existing one.

Second, `REPLACE` has a `KEEP` clause that lets you keep *some* attribute values
from the existing (aka committed!) rows. For non-existing rows, the respective
columns will be filled with default values.

`KEEP` columns must be attributes, and not fields. You can't "keep" any fields.
All the attributes types are supported, so you can "keep" numeric values, or
strings, or JSONs, etc.

`KEEP` columns must **not** be mentioned in the explicit column list, if you
have one. Because, naturally, you're either inserting a certain new value, or
keeping an old one.

When **not** using an explicit column list, the number of expected `VALUES`
changes. It gets adjusted for `KEEP` clause, meaning that you must **not** put
the columns you're keeping in your `VALUES` entries. Here's an example.

```sql
create table test (id bigint, title field_string, k1 uint, k2 uint);
insert into test values (123, 'version one', 1, 1);
replace into test values (123, 'version two', 2, 2);
replace into test values (123, 'version three', 3) keep (k1); -- changes k2
replace into test values (123, 'version four', 4) keep (k2); -- changes k1
```

Note how we're "normally" inserting all 4 columns, but with `KEEP` we omit
whatever we're keeping, and so we must provide just 3 columns. For the record,
let's check the final result.

```
mysql> select * from test;
+------+--------------+------+------+
| id   | title        | k1   | k2   |
+------+--------------+------+------+
|  123 | version four |    4 |    3 |
+------+--------------+------+------+
1 row in set (0.00 sec)
```

Well, everything as expected. In version 3 we kept `k1`, it got excluded from
our explicit columns list, and the value 3 landed into `k2`. Then in version 4
we kept `k2`, the value 4 landed into `k1`, replacing the previous value (which
was 2).

Existing rows mean **committed** rows. So the following pseudo-transaction
results in the **index** value 3 being kept, not the in-transaction value 55.

```sql
begin;
replace into test values (123, 'version 5', 55, 55);
replace into test values (123, 'version 6', 66) keep (k2);
commit;
```
```
mysql> select * from test;
+------+-----------+------+------+
| id   | title     | k1   | k2   |
+------+-----------+------+------+
|  123 | version 6 |   66 |    3 |
+------+-----------+------+------+
1 row in set (0.00 sec)
```


### SELECT syntax

```sql
SELECT <expr> [BETWEEN <min> AND <max>] [[AS] <alias>] [, ...]
FROM <ftindex> [, ...]
    [{USE | IGNORE | FORCE} INDEX (<attr_index> [, ...]) [...]]
[WHERE
    [MATCH('<text_query>') [AND]]
    [<where_condition> [AND <where_condition> [...]]]]
[GROUP [<N>] BY <column> [, ...]
    [WITHIN GROUP ORDER BY <column> {ASC | DESC} [, ...]]
    [HAVING <having_condition>]]
[ORDER BY <column> {ASC | DESC} [, ...]]
[LIMIT [<offset>,] <row_count>]
[OPTION <opt_name> = <opt_value> [, ...]]
[FACET <facet_options> [...]]
```

`SELECT` is the main querying workhorse, and as such, comes with a rather
extensive (and perhaps a little complicated) syntax. There are many different
parts (aka clauses) in that syntax. Thankfully, most of them are optional.

Briefly, they are as follows:

  * required `SELECT` columns list (aka items list, aka expressions list)
  * required `FROM` clause, with the full-text index list
  * optional `<hint> INDEX` clauses, with the attribute index usage hints
  * optional `WHERE` condition clause, with the row filtering conditions
  * optional `GROUP BY` clause, with the row grouping conditions
  * optional `ORDER BY` clause, with the row sorting conditions
  * optional `LIMIT` clause, with the result set size and offset
  * optional `OPTION` clause, with all the special options
  * optional `FACET` clauses, with a list of requested additional facets

The most notable differences from regular SQL are these:

  * `FROM` list is **NOT** an implicit `JOIN`, but more like a `UNION`
  * `ORDER BY` is always present, default is `ORDER BY WEIGHT() DESC, id ASC`
  * `LIMIT` is always present, default is `LIMIT 0,20`
  * `GROUP BY` always picks a specific "best" row to represent the group

#### Index hints clause

Index hints can be used to tweak query optimizer behavior and attribute index
usage, for either performance or debugging reasons. Note that usually you should
*not* have to use them.

Multiple hints can be used, and multiple attribute indexes can be listed, in any
order. For example, the following syntax is legal:

```sql
SELECT id FROM test1
USE INDEX (idx_lat)
FORCE INDEX (idx_price)
IGNORE INDEX (idx_time)
USE INDEX (idx_lon) ...
```

All flavors of `<hint> INDEX` clause take an index list as their argument, for
example:

```sql
... USE INDEX (idx_lat, idx_lon, idx_price)
```

Summarily, hints work this way:

  * `USE INDEX` limits the optimizer to only use a subset of given indexes;
  * `IGNORE INDEX` strictly forbids given indexes from being used;
  * `FORCE INDEX` strictly forces the given indexes to be used.

`USE INDEX` tells the optimizer that it must only consider the given indexes,
rather than *all* the applicable ones. In other words, in the absence of the
`USE` clause, all indexes are fair game. In its presence, only those that were
mentioned in the `USE` list are. The optimizer still decides whether to actually
to use or ignore any specific index. In the example above it still might choose
to use `idx_lat` only, but it must never use `idx_time`, on the grounds that it
was not mentioned explicitly.

`IGNORE INDEX` completely forbids the optimizer from using the given indexes.
Ignores take priority, they override both `USE INDEX` and `FORCE INDEX`. Thus,
while it is legal to `USE INDEX (foo, bar) IGNORE INDEX (bar)`, it is way too
verbose. Simple `USE INDEX (foo)` achieves exactly the same result.

`FORCE INDEX` makes the optimizer forcibly use the given indexes (that is, if
they are applicable at all) despite the query cost estimates.

For more discussion and details on attributes indexes and hints, refer to
["Using attribute indexes"](#using-attribute-indexes).

#### Star expansion quirks

Ideally any stars (as in `SELECT *`) would just expand to "all the columns" as
in regular SQL. Except that Sphinx has a couple peculiarities worth a mention.

**Stars skip the indexed-only fields.** Fields that are not anyhow stored
(either in an attribute or in DocStore) can not be included in `SELECT`, and
will not be included in the star expansion.

While Sphinx *lets* one store the original field content, it still does not
*require* that. So the fields can be full-text indexed, but not stored in any
way, shape, or form. Moreover, that still is the default behavior.

In SphinxQL terms these indexed-only fields are columns that one perfectly can
(and should) `INSERT` to, but can not `SELECT` from, and they are not included
in the star expansion. Because the original field content to return does not
even exist. Only the full-text index does.

**Stars skip the already-selected columns.** Star expansion currently skips any
columns that are explicitly selected *before* the star.

For example, assume that we run `SELECT cc,ee,*` from an index with 5 attributes
named `aa` to `ee` (and of course the required `id` too). We would expect to get
a result set with 8 columns ordered `cc,ee,id,aa,bb,cc,dd,ee` here. But in fact
Sphinx would return just 6 columns in the `cc,ee,id,aa,bb,dd` order. Because of
this "skip the explicit dupes" quirk.

For the record, this was a requirement a while ago, the result set column names
were required to be unique. Today it's only a legacy implementation quirk, going
to be eventually fixed.

#### SELECT options

Here's a brief summary of all the (non-deprecated) options that `SELECT`
supports.

| Option                | Description                                  | Type     | Default        |
|-----------------------|----------------------------------------------|----------|----------------|
| agent_query_timeout   | Max agent query timeout, in msec             | int      | 3000           |
| boolean_simplify      | Use boolean query simplification             | bool     | 0              |
| comment               | Set user comment (gets logged!)              | string   | ''             |
| cutoff                | Max matches to process per-index             | int      | 0              |
| expansion_limit       | Per-query keyword expansion limit            | int      | 0              |
| field_weights         | Per-field weights map                        | map      | (...)          |
| global_idf            | Enable global IDF                            | bool     | 0              |
| index_weights         | Per-index weights map                        | map      | (...)          |
| inner_limit_per_index | Forcibly use per-index inner LIMIT           | bool     | 0              |
| lax_agent_errors      | Lax agent error handling (treat as warnings) | bool     | 0              |
| local_df              | Compute IDF over all the local query indexes | bool     | 0              |
| low_priority          | Use a low priority thread                    | bool     | 0              |
| max_predicted_time    | Impose a virtual time limit, in units        | int      | 0              |
| max_query_time        | Impose a wall time limit, in msec            | int      | 0              |
| rand_seed             | Use a specific RAND() seed                   | int      | -1             |
| rank_fields           | Use the listed fields only in FACTORS()      | string   | ''             |
| ranker                | Use a given ranker function (and expression) | enum     | proximity_bm15 |
| retry_count           | Max agent query retries count                | int      | 0              |
| retry_delay           | Agent query retry delay, in msec             | int      | 500            |
| sample_div            | Enable sampling with this divisor            | int      | 0              |
| sample_min            | Start sampling after this many matches       | int      | 0              |
| sort_mem              | Per-sorter memory budget, in bytes           | size     | 50M            |
| sort_method           | Match sorting method (`pq` or `kbuffer`)     | enum     | pq             |

Most of the options take integer values. Boolean flags such as `global_idf` also
take integers, either 0 (off) or 1 (on). For convenience, `sort_mem` budget
option takes either an integer value in bytes, or with a size postfix (K/M/G).

`field_weights` and `index_weights` options take a map that maps names to
(integer) values, as follows:
```sql
... OPTION field_weights=(title=10, content=3)
```

`rank_fields` option takes a list of fields as a string, for example:
```sql
... OPTION rank_fields='title content'
```

#### Index sampling

You can get sampled search results using the `sample_div` and `sample_min`
options, usually in a fraction of time compared to the regular, "full" search.
The key idea is to only process every N-th row at the lowest possible level, and
skip everything else.

To enable index sampling simply set the `sample_div` divisor to anything greater
or equal than 2. For example, the following runs a query over approximately 5%
of the entire index.

```sql
SELECT id, WEIGHT() FROM test1 WHERE MATCH('hello world')
OPTION sample_div=20
```

To initially pause sampling additionally set the `sample_min` threshold to
anything greater than the default 0. Sampling will then only engage later, once
`sample_min` matches are collected. So, naturally, sampled result sets up to
`sample_min` matches (inclusive) must be exact. For example.

```sql
SELECT id, WEIGHT() FROM test1 WHERE MATCH('hello world')
OPTION sample_div=20, sample_min=1000
```

Sampling works with distributed indexes too. However, in that case, the minimum
threshold applies to each component index. For example, if `test1` is actually
a distributed index with 4 shards in the example above, then *each* shard will
collect 1000 matches first, and then only sample every 20-th row next.

Last but not least, **beware that sampling works on rows and NOT matches!**
The sampled result is equivalent to running the query against a sampled index
built from a fraction of the data (every N-th row, where N is `sample_div`).
**Non-sampled rows are skipped very early, even before matching.**

And this *is* somewhat different from sampling the final results. If your
`WHERE` conditions are heavily correlated with the sampled rowids, then the
sampled results might be severely biased (as in, way off).

Here's an extreme example of that bias. What if we have an index with 1 million
documents having almost sequential docids (with just a few numbering gaps), and
filter on a docid remainder using the very same divisor as with sampling?!

```sql
mysql> SELECT id, id%10 rem FROM test1m WHERE rem=3
    -> LIMIT 5 OPTION sample_div=10;
Empty set (0.10 sec)
```

Well, in the extreme example the results are *extremely* skewed. Without
sampling, we *do* get about 100K matches from that query (99994 to be precise).
With 1/10-th sampling, normally we would expect (and get!) about 10K matches.

Except that "thanks" to the heavily correlated (practically dependent) condition
we get 0 matches! Way, waaay off. Well, it's as if we were searching for "odd"
docids in the "even" half of the index. Of course we would get zero matches.

But once we tweak the divisor just a little and decorrelate, the situation is
immediately back to normal.

```sql
mysql> SELECT id, id%10 rem FROM test1m WHERE rem=3
    -> LIMIT 3 OPTION sample_div=11;
+------+------+
| id   | rem  |
+------+------+
|   23 |    3 |
|  133 |    3 |
|  243 |    3 |
+------+------+
3 rows in set (0.08 sec)

mysql> SHOW META like 'total_found';
+---------------+-------+
| Variable_name | Value |
+---------------+-------+
| total_found   | 9090  |
+---------------+-------+
1 row in set (0.00 sec)
```

Actually, ideal sampling, that. Instead of a complete and utter miss we had just
before. (For the record, as the exact count is 99994, so any `total_found` from
9000 to 9180 would still be within a very reasonable 1% margin of error away
from the ideal 9090 sample size.) Bottom line, beware of the correlations and
take good care of them.


### SELECT expr syntax

```sql
SELECT <expression>
```

This special `SELECT` form lets you use Sphinx as a calculator, and evaluate
an individual expression on Sphinx side. For instance!

```sql
mysql> select sin(1)+2;
+----------+
| sin(1)+2 |
+----------+
| 2.841471 |
+----------+
1 row in set (0.00 sec)

mysql> select crc32('eisenhower');
+---------------------+
| crc32('eisenhower') |
+---------------------+
| -804052648          |
+---------------------+
1 row in set (0.00 sec)
```


### SELECT @uservar syntax

```sql
SELECT <@uservar>
```

This special `SELECT` form lets you examine a specific user variable. Unknown
variable will return NULL. Known variable will return its value.

```sql
mysql> set global @foo=(9,1,13);
Query OK, 0 rows affected (0.00 sec)

mysql> select @foo;
+----------+
| @foo     |
+----------+
| (1,9,13) |
+----------+
1 row in set (0.00 sec)

mysql> select @bar;
+------+
| @bar |
+------+
| NULL |
+------+
1 row in set (0.00 sec)
```


### SELECT @@sysvar syntax

```sql
SELECT <@@sysvar> [LIMIT [<offset> ,] row_count]
```

This special `SELECT` form is a placeholder that does nothing. This is just for
compatibility with frameworks and/or MySQL client libraries that automatically
execute this kind of statement.


### SHOW CREATE TABLE syntax

```sql
SHOW CREATE TABLE <ftindex>
```

This statement prints a `CREATE TABLE` statement matching the given full-text
index schema and settings. It works for both plain and RT indexes.

The initial purpose of this statement was to support `mysqldump` which requires
at least *some* `CREATE TABLE` text.

However, it should also be a useful tool to examine index settings on the fly,
because it also prints out any non-default settings.

```
mysql> SHOW CREATE TABLE jsontest \G
*************************** 1. row ***************************
       Table: jsontest
Create Table: CREATE TABLE jsontest (
  id bigint,
  title field indexed,
  content field indexed,
  uid bigint,
  j json
)
OPTION rt_mem_limit = 10485760,
  min_infix_len = 3
1 row in set (0.00 sec)
```


### SHOW INDEX AGENT STATUS syntax

```sql
SHOW INDEX <distindex> AGENT STATUS [LIKE '...']
```

`SHOW INDEX AGENT STATUS` lets you examine a number internal per-agent counters
associated with every agent (and then every mirror host of an agent) in a given
distributed index.

The agents are numbered in the config order. The mirrors within each agent are
also numbered in the config order. All timers must internally have microsecond
precision, but should be displayed as floats and in milliseconds, for example:

```sql
mysql> SHOW INDEX dist1 AGENT STATUS LIKE '%que%';
+--------------------------------+-------+
| Variable_name                  | Value |
+--------------------------------+-------+
| agent1_host1_query_timeouts    | 0     |
| agent1_host1_succeeded_queries | 1     |
| agent1_host1_total_query_msec  | 2.943 |
| agent2_host1_query_timeouts    | 0     |
| agent2_host1_succeeded_queries | 1     |
| agent2_host1_total_query_msec  | 3.586 |
+--------------------------------+-------+
6 rows in set (0.00 sec)
```

As we can see from the output, there was just 1 query sent to each agent since
`searchd` start, that query went well on both agents, and it took approx 2.9 ms
and 3.6 ms respectively. The specific agents are addresses are intentionally not
part of this status output to avoid clutter; they can in turn be examined using
`DESCRIBE` statement:

```sql
mysql> DESC dist1
+---------------------+----------+
| Agent               | Type     |
+---------------------+----------+
| 127.0.0.1:7013:loc1 | remote_1 |
| 127.0.0.1:7015:loc2 | remote_2 |
+---------------------+----------+
2 rows in set (0.00 sec)
```

In this case (ie. without mirrors) the mapping is straightforward, we can see
that we only have two agents, `agent1` on port 7013 and `agent2` on port 7015,
and we now know what statistics are associated with which agent exactly. Easy.


### SHOW INDEX FROM syntax

```sql
SHOW INDEX FROM <ftindex>
```

`SHOW INDEX` lists all attribute indexes from the given FT index, along with
their types, and column names or JSON paths (where applicable). For example:

```
mysql> SHOW INDEX FROM test;
+------+----------------+----------+-------+-------------+
| No   | IndexName      | AttrName | Type  | Expr        |
+------+----------------+----------+-------+-------------+
| 1    | idx_json       | tag_json | uint  | tag_json[0] |
| 2    | idx_json_float | tag_json | float | tag_json[1] |
+------+----------------+----------+-------+-------------+
2 rows in set (0.00 sec)
```

Note that just the attribute indexes names for the given FT index can be listed
by both `SHOW INDEX` and `DESCRIBE` statements:

```
mysql> DESCRIBE test;
+----------+--------+------------+--------------------------+
| Field    | Type   | Properties | Key                      |
+----------+--------+------------+--------------------------+
| id       | bigint |            |                          |
| title    | field  | indexed    |                          |
| tag_json | json   |            | idx_json, idx_json_float |
+----------+--------+------------+--------------------------+
3 rows in set (0.00 sec)
```

However, `SHOW INDEX` also provides additional details, namely the value type,
and the exact JSON expression indexed. (As a side note, for "simple" indexes on
non-JSON columns, `Expr` just equals `AttrName`.)


### SHOW OPTIMIZE STATUS syntax

```sql
SHOW OPTIMIZE STATUS [LIKE '<varmask>']
```

This statement shows the status of current full-text index `OPTIMIZE` requests
queue, in a human-readable format, as follows.

```sql
+--------------------+-------------------------------------------------------------------+
| Variable_name      | Value                                                             |
+--------------------+-------------------------------------------------------------------+
| index_1_name       | rt2                                                               |
| index_1_start      | 2023-07-06 23:35:55                                               |
| index_1_progress   | 0 of 2 disk segments done, merged to 0.0 Kb, 1.0 Kb left to merge |
| total_in_progress  | 1                                                                 |
| total_queue_length | 0                                                                 |
+--------------------+-------------------------------------------------------------------+
5 rows in set (0.00 sec)
```


### SHOW PROFILE syntax

```sql
SHOW PROFILE [LIKE '<varmask>']
```

`SHOW PROFILE` statement shows a detailed execution profile for the most recent
(profiled) SQL statement in the current SphinxQL session.

You **must** explicitly enable profiling first, by running a `SET profiling=1`
statement. Profiles are disabled by default to avoid any performance impact.

The optional `LIKE '<varmask>'` clause lets you pick just the profile entries
matching the given mask (aka wildcard).

Profiles should work on distributed indexes too, and aggregate the timings
across all the agents.

Here's a complete instrumentation example.

```sql
mysql> SET profiling=1;
Query OK, 0 rows affected (0.00 sec)

mysql> SELECT id FROM lj WHERE MATCH('the test') LIMIT 1;
+--------+
| id     |
+--------+
| 946418 |
+--------+
1 row in set (0.03 sec)

mysql> SHOW PROFILE;
+--------------+----------+----------+---------+
| Status       | Duration | Switches | Percent |
+--------------+----------+----------+---------+
| unknown      | 0.000278 | 6        | 0.55    |
| local_search | 0.025201 | 1        | 49.83   |
| sql_parse    | 0.000041 | 1        | 0.08    |
| dict_setup   | 0.000000 | 1        | 0.00    |
| parse        | 0.000049 | 1        | 0.10    |
| transforms   | 0.000005 | 1        | 0.01    |
| init         | 0.000242 | 2        | 0.48    |
| read_docs    | 0.000315 | 2        | 0.62    |
| read_hits    | 0.000080 | 2        | 0.16    |
| get_docs     | 0.014230 | 1954     | 28.14   |
| get_hits     | 0.007491 | 1352     | 14.81   |
| filter       | 0.000263 | 904      | 0.52    |
| rank         | 0.002076 | 2687     | 4.11    |
| sort         | 0.000283 | 219      | 0.56    |
| finalize     | 0.000000 | 1        | 0.00    |
| aggregate    | 0.000018 | 2        | 0.04    |
| eval_post    | 0.000000 | 1        | 0.00    |
| total        | 0.050572 | 7137     | 0       |
+--------------+----------+----------+---------+
18 rows in set (0.00 sec)

mysql> show profile like 'read_%';
+-----------+----------+----------+---------+
| Status    | Duration | Switches | Percent |
+-----------+----------+----------+---------+
| read_docs | 0.000315 | 2        | 0.62    |
| read_hits | 0.000080 | 2        | 0.16    |
+-----------+----------+----------+---------+
2 rows in set (0.00 sec)
```

"Status" column briefly describes how exactly (in which execution state) was the
time spent.

"Duration" column shows the total wall clock time taken (by the respective
state), in seconds.

"Switches" column shows how many times the engine switched to this state. Those
are just logical engine state switches and *not* any OS level context switches
nor even function calls. So they do not necessarily have any direct effect on
the performance, and having lots of switches (thousands or even millions) is not
really an issue per se. Because, essentially, this is just a number of times
when the respective instrumentation point was hit.

"Percent" column shows the relative state duration, as percentage of the total
time profiled.

At the moment, the profile states are returned in a certain prerecorded order
that roughly maps (but is not completely identical) to the actual query order.

A list of states varies over time, as we refine it. Here's a brief description
of the current profile states.

| State        | Description                                                                  |
|--------------|------------------------------------------------------------------------------|
| aggregate    | aggregating multiple result sets                                             |
| dict_setup   | setting up the dictionary and tokenizer                                      |
| dist_connect | distributed index connecting to remote agents                                |
| dist_wait    | distributed index waiting for remote agents results                          |
| eval_post    | evaluating special post-LIMIT expressions (except snippets)                  |
| eval_snippet | evaluating snippets                                                          |
| eval_udf     | evaluating UDFs                                                              |
| filter       | filtering the full-text matches                                              |
| finalize     | finalizing the per-index search result set (last stage expressions, etc)     |
| fullscan     | executing the "fullscan" (more formally, non-full-text) search               |
| get_docs     | computing the matching documents                                             |
| get_hits     | computing the matching positions                                             |
| init         | setting up the query evaluation in general                                   |
| init_attr    | setting up attribute index(-es) usage                                        |
| init_segment | setting up RT segments                                                       |
| io           | generic file IO time (deprecated)                                            |
| local_df     | setting up `local_df` values, aka the "sharded" IDFs                         |
| local_search | executing local query (for distributed and sharded cases)                    |
| net_read     | network reads (usually from the client application)                          |
| net_write    | network writes (usually to the client application)                           |
| open         | opening the index files                                                      |
| parse        | parsing the full-text query syntax                                           |
| rank         | computing the ranking signals and/or the relevance rank                      |
| read_docs    | disk IO time spent reading document lists                                    |
| read_hits    | disk IO time spent reading keyword positions                                 |
| sort         | sorting the matches                                                          |
| sql_parse    | parsing the SphinxQL syntax                                                  |
| table_func   | processing table functions                                                   |
| transforms   | full-text query transformations (wildcard expansions, simplification, etc)   |
| unknown      | generic catch-all state: not-yet-profiled code plus misc "too small" things  |

The final entry is always "total" and it reports the sums of all the profiled
durations and switches respectively. Percentage is intentionally reported as 0
rather than 100 because "total" is not a real execution state.


### SHOW STATUS syntax

```sql
SHOW [INTERNAL] STATUS [LIKE '<varmask>']
```

`SHOW STATUS` displays a number of useful server-wide performance and statistics
counters. Those are (briefly) documented just below, and should be generally
useful for health checks, monitoring, etc.

In `SHOW INTERNAL STATUS` mode, however, it only displays a few currently
experimental internal counters. Those counters might or might not later make it
into GA releases, and are intentionally **not** documented here.

All the aggregate counters (ie. total this, average that) are since startup.

Several IO and CPU counters are only available when you start `searchd` with
explicit `--iostats` and `--cpustats` accounting switches, respectively. Those
are not enabled by default because of a measurable performance impact.

Zeroed out or disabled counters can be intentionally omitted from the output,
for brevity. For instance, if the server did not ever see any `REPLACE` queries
via SphinxQL, the respective `sql_replace` counter will be omitted.

`LIKE '<varmask>'` condition is supported and functional, for instance:

```sql
mysql> show status like 'local%';
+------------------------+---------+
| Counter                | Value   |
+------------------------+---------+
| local_indexes          | 6       |
| local_indexes_disabled | 5       |
| local_docs             | 2866967 |
| local_disk_mb          | 2786.2  |
| local_ram_mb           | 1522.0  |
+------------------------+---------+
5 rows in set (0.00 sec)
```

Quick counters reference is as follows.

| Counter                | Description                                                                                   |
|------------------------|-----------------------------------------------------------------------------------------------|
| agent_connect          | Total remote agent connection attempts                                                         |
| agent_retry            | Total remote agent query retry attempts                                                       |
| avg_dist_local         | Average time spent querying local indexes in queries to distributed indexes, in seconds       |
| avg_dist_wait          | Average time spent waiting for remote agents in queries to distributed indexes, in seconds    |
| avg_dist_wall          | Average overall time spent in queries to distributed indexes, in seconds                      |
| avg_query_cpu          | Average CPU time spent per query (as reported by OS; requires `--cpustats`)                   |
| avg_query_readkb       | Average bytes read from disk per query, in KiB (KiB is 1024 bytes; requires `--iostats`)      |
| avg_query_reads        | Average disk `read()` calls per query (requires `--iostats`)                                  |
| avg_query_readtime     | Average time per `read()` call, in seconds (requires `--iostats`)                             |
| avg_query_wall         | Average elapsed query time, in seconds                                                        |
| command_XXX            | Total number of SphinxAPI "XXX" commands (for example, `command_search`)                      |
| connections            | Total accepted network connections                                                            |
| dist_local             | Total time spent querying local indexes in queries to distributed indexes, in seconds         |
| dist_predicted_time    | Total predicted query time (in msec) reported by remote agents                                |
| dist_queries           | Total queries to distributed indexes                                                          |
| dist_wait              | Total time spent waiting for remote agents in queries to distributed indexes, in seconds      |
| dist_wall              | Total time spent in queries to distributed indexes, in seconds                                |
| killed_queries         | Total queries that were auto-killed on client network failure                                 |
| local_disk_mb          | Total disk use over all enabled local indexes, in MB (MB is 1 million bytes)                  |
| local_docs             | Total document count over all enabled local indexes                                           |
| local_indexes          | Total enabled local indexes (both plain and RT)                                               |
| local_indexes_disabled | Total disabled local indexes                                                                  |
| local_ram_mb           | Total RAM use over all enabled local indexes, in MB (MB is 1 million bytes)                   |
| maxed_out              | Total accepted network connections forcibly closed because the server was maxed out           |
| predicted_time         | Total predicted query time (in msec) report by local searches                                 |
| qcache_cached_queries  | Current number of queries stored in the query cache                                           |
| qcache_hits            | Total number of query cache hits                                                              |
| qcache_used_bytes      | Current query cache storage size, in bytes                                                    |
| queries                | Total number of search queries served (either via SphinxAPI or SphinxQL)                      |
| query_cpu              | Total CPU time spent on search queries, in seconds (as reported by OS; requires `--cpustats`) |
| query_readkb           | Total bytes read from disk by queries, in KiB (KiB is 1024 bytes; requires `--iostats`)       |
| query_reads            | Total disk `read()` calls by queries (requires `--iostats`)                                   |
| query_readtime         | Total time spend in `read()` call by queries, in seconds (requires `--iostats`)               |
| query_wall             | Total elapsed search queries time, in seconds                                                 |
| siege_sec_left         | Current time left until "siege mode" auto-expires, in seconds                                 |
| sql_XXX                | Total number of SphinxQL "XXX" statements (for example, `sql_select`)                         |
| uptime                 | Uptime, in seconds                                                                            |
| work_queue_length      | Current thread pool work queue length (ie. number of jobs waiting for workers)                |
| workers_active         | Current number of active thread pool workers                                                  |
| workers_total          | Total thread pool workers count                                                               |

Last but not least, here goes some example output, taken from v.3.4. Beware,
it's a bit longish.

```sql
mysql> SHOW STATUS;
+------------------------+---------+
| Counter                | Value   |
+------------------------+---------+
| uptime                 | 25      |
| connections            | 1       |
| maxed_out              | 0       |
| command_search         | 0       |
| command_snippet        | 0       |
| command_update         | 0       |
| command_delete         | 0       |
| command_keywords       | 0       |
| command_persist        | 0       |
| command_status         | 3       |
| command_flushattrs     | 0       |
| agent_connect          | 0       |
| agent_retry            | 0       |
| queries                | 0       |
| dist_queries           | 0       |
| killed_queries         | 0       |
| workers_total          | 20      |
| workers_active         | 1       |
| work_queue_length      | 0       |
| query_wall             | 0.000   |
| query_cpu              | OFF     |
| dist_wall              | 0.000   |
| dist_local             | 0.000   |
| dist_wait              | 0.000   |
| query_reads            | OFF     |
| query_readkb           | OFF     |
| query_readtime         | OFF     |
| avg_query_wall         | 0.000   |
| avg_query_cpu          | OFF     |
| avg_dist_wall          | 0.000   |
| avg_dist_local         | 0.000   |
| avg_dist_wait          | 0.000   |
| avg_query_reads        | OFF     |
| avg_query_readkb       | OFF     |
| avg_query_readtime     | OFF     |
| qcache_cached_queries  | 0       |
| qcache_used_bytes      | 0       |
| qcache_hits            | 0       |
| sql_parse_error        | 1       |
| sql_show_status        | 3       |
| local_indexes          | 6       |
| local_indexes_disabled | 5       |
| local_docs             | 2866967 |
| local_disk_mb          | 2786.2  |
| local_ram_mb           | 1522.0  |
+------------------------+---------+
44 rows in set (0.00 sec)
```


### SHOW THREADS syntax

```sql
SHOW THREADS [OPTION columns = <width>]
```

`SHOW THREADS` shows all the currently active client worker threads, along with
the thread states, queries they are executing, elapsed time, and so on. (Note
that there also always are internal system threads. Those are *not* shown.)

This is quite useful for troubleshooting (generally taking a peek at what
exactly is the server doing right now; identifying problematic query patterns;
killing off individual "runaway" queries, etc). Here's a simple example.

```sql
mysql> SHOW THREADS OPTION columns=50;
+------+----------+------+-------+----------+----------------------------------------------------+
| Tid  | Proto    | User | State | Time     | Info                                               |
+------+----------+------+-------+----------+----------------------------------------------------+
| 1181 | sphinxql |      | query | 0.000001 | show threads option columns=50                     |
| 1177 | sphinxql |      | query | 0.000148 | select * from rt option comment='fullscan'         |
| 1168 | sphinxql |      | query | 0.005432 | select * from rt where m ... comment='text-search' |
| 1132 | sphinxql |      | query | 0.885282 | select * from testwhere match('the')               |
+------+----------+------+-------+----------+----------------------------------------------------+
4 row in set (0.00 sec)
```

The columns are:

| Column | Description                                                           |
|--------|-----------------------------------------------------------------------|
| Tid    | Internal thread ID, can be passed to KILL                             |
| Proto  | Client connection protocol, `sphinxapi` or `sphinxql` or `http`       |
| User   | Client user name, as in `auth_users` (if enabled)                     |
| State  | Thread state, `{handshake | net_read | net_write | query | net_idle}` |
| Time   | Time spent in current state, in seconds, with microsecond precision   |
| Info   | Query text, or other available data                                   |

"Info" is usually the most interesting part. With SphinxQL it basically shows
the raw query text; with SphinxAPI the full-text query, comment, and data size;
and so on.

`OPTION columns = <width>` enforces a limit on the "Info" column width. That
helps with concise overviews when the queries are huge.

The default width is 4 KB, or 4096 bytes. The minimum width is set at 10 bytes.
There always is *some* width limit, because queries can get *extremely* long.
Say, consider a big batch `INSERT` that spans several megabytes. We would pretty
much never want its *entire* content dumped by `SHOW THREADS`, hence the limit.

Comments (as in `OPTION comment`) are prioritized when cutting SphinxQL queries
down to the requested width. If the comment can fit at all, we do that, even if
that means removing everything else. In the example above that's exactly what
happens in the 3rd row. Otherwise, we simply truncate the query.


### SHOW VARIABLES syntax

```sql
SHOW [{GLOBAL | SESSION}] VARIABLES
    [{WHERE variable_name='<varname>' [OR ...] |
    LIKE '<varmask>'}]
```

`SHOW VARIABLES` statement serves two very different purposes:

  * to provide compatibility with 3rd party MySQL clients;
  * to examine the current status of `searchd` server variables.

Compatibility mode is required to support connections from certain MySQL clients
that automatically run `SHOW VARIABLES` on connection and fail if that statement
raises an error.

At the moment, optional `GLOBAL` or `SESSION` scope condition syntax is used for
MySQL compatibility only. But Sphinx ignores the scope, and all variables, both
global and per-session, are always displayed.

`WHERE variable_name ...` condition is also for compatibility only, and also
ignored.

`LIKE '<varmask>'` condition is supported and functional, for instance:

```sql
mysql> show variables like '%comm%';
+---------------+-------+
| Variable_name | Value |
+---------------+-------+
| autocommit    | 1     |
+---------------+-------+
1 row in set (0.00 sec)
```

Some of the variables displayed in `SHOW VARIABLES` are *mutable*, and can be
changed on the fly using the `SET GLOBAL` statement. For example, you can tweak
`log_level` or `sql_log_file` on the fly.

Some are *read-only* though, that is, they can be changed, but only by editing
the config file and restarting the daemon. For example, `max_allowed_packet` and
`listen` are read-only. You can only change them in `sphinx.conf` and restart.

And finally, some of the variiables are *constant*, compiled into the binary and
never changed, such as `version` and a few more informational variables.

```sql
mysql> show variables;
+------------------------------+-------------------------------------+
| Variable_name                | Value                               |
+------------------------------+-------------------------------------+
| agent_connect_timeout        | 1000                                |
| agent_query_timeout          | 3000                                |
| agent_retry_delay            | 500                                 |
| attrindex_thresh             | 1024                                |
| autocommit                   | 1                                   |
| binlog_flush_mode            | 2                                   |
| binlog_max_log_size          | 0                                   |
| binlog_path                  |                                     |
| character_set_client         | utf8                                |
| character_set_connection     | utf8                                |
| client_timeout               | 300                                 |
| collation_connection         | libc_ci                             |
| collation_libc_locale        |                                     |
| dist_threads                 | 0                                   |
| docstore_cache_size          | 10485760                            |
| expansion_limit              | 0                                   |
| ha_period_karma              | 60                                  |
| ha_ping_interval             | 1000                                |
| ha_weight                    | 100                                 |
| hostname_lookup              | 0                                   |
| listen                       | 9380:http                           |
| listen                       | 9306:mysql41                        |
| listen                       | 9312                                |
| listen_backlog               | 64                                  |
| log                          | ./data/searchd.log                  |
| log_debug_filter             |                                     |
| log_level                    | info                                |
| max_allowed_packet           | 8388608                             |
| max_batch_queries            | 32                                  |
| max_children                 | 20                                  |
| max_filter_values            | 4096                                |
| max_filters                  | 256                                 |
| my_net_address               |                                     |
| mysql_version_string         | 3.4.1-dev (commit 6d01467e1)        |
| net_spin_msec                | 10                                  |
| net_throttle_accept          | 0                                   |
| net_throttle_action          | 0                                   |
| net_workers                  | 1                                   |
| ondisk_attrs_default         | 0                                   |
| persistent_connections_limit | 0                                   |
| pid_file                     |                                     |
| plugin_dir                   |                                     |
| predicted_time_costs         | doc=64, hit=48, skip=2048, match=64 |
| preopen_indexes              | 0                                   |
| qcache_max_bytes             | 0                                   |
| qcache_thresh_msec           | 3000                                |
| qcache_ttl_sec               | 60                                  |
| query_log                    | ./data/query.log                    |
| query_log_format             | sphinxql                            |
| query_log_min_msec           | 0                                   |
| queue_max_length             | 0                                   |
| read_buffer                  | 0                                   |
| read_timeout                 | 5                                   |
| read_unhinted                | 0                                   |
| rt_flush_period              | 36000                               |
| rt_merge_iops                | 0                                   |
| rt_merge_maxiosize           | 0                                   |
| seamless_rotate              | 0                                   |
| shutdown_timeout             | 3000000                             |
| siege                        | 0                                   |
| siege_max_fetched_docs       | 1000000                             |
| siege_max_query_msec         | 1000                                |
| snippets_file_prefix         |                                     |
| sphinxql_state               | state.sql                           |
| sphinxql_timeout             | 900                                 |
| sql_fail_filter              |                                     |
| sql_log_file                 |                                     |
| thread_stack                 | 131072                              |
| unlink_old                   | 1                                   |
| version                      | 3.4.1-dev (commit 6d01467e1)        |
| version_api_master           | 23                                  |
| version_api_search           | 1.34                                |
| version_binlog_format        | 8                                   |
| version_index_format         | 55                                  |
| version_udf_api              | 17                                  |
| watchdog                     | 1                                   |
| workers                      | 1                                   |
+------------------------------+-------------------------------------+
```

Specific per-variable documentation can be found in the
["Server variables reference"](#server-variables-reference) section.


### UPDATE syntax

```sql
UPDATE [INPLACE] <ftindex> SET <col1> = <val1> [, <col2> = <val2> [...]]
WHERE <where_cond> [OPTION opt_name = opt_value [, ...]]
```

`UPDATE` lets you update existing FT indexes with new column (aka attribute)
values. **The new values must be constant and explicit**, ie. expressions such
as `UPDATE ... SET price = price + 10 ...` are *not* (yet) supported. You need
to use `SET price = 100` instead. Multiple columns can be updated at once,
though, ie. `SET price = 100, quantity = 15` is okay.

**Updates work with both RT and plain indexes**, as they only modify attributes
and not the full-text fields.

As of v.3.5 **most attributes types can be updated.** The current exceptions are
blobs, and *some* of the JSON updates (namely, non-inplace partial updates that
change the JSON column length). There's work in progress to support those too.

**Rows to update must be selected using the `WHERE` condition clause.** Refer to
[`SELECT` statement](#select-syntax) for its syntax details.

**The new values are type-checked and range-checked.** For instance, attempts to
update an `UINT` column with floats or too-big integers should fail.

```sql
mysql> UPDATE rt SET c1=1.23 WHERE id=123;
ERROR 1064 (42000): index 'rt': attribute 'c1' is integer
and can not store floating-point values

mysql> UPDATE rt SET c1=5000111222 WHERE id=123;
ERROR 1064 (42000): index 'rt': value '5000111222' is out of range
and can not be stored to UINT
```

We do not (yet!) claim complete safety here, some edge cases may have slipped
through the cracks. So if you find any, please report them.

**MVA values must be specified as comma-separated lists in parentheses.** And to
erase a MVA value just use an empty list, ie. `()`. For the record, MVA updates
are naturally non-inplace.

```sql
mysql> UPDATE rt SET m1=(3,6,4), m2=()
    -> WHERE MATCH('test') AND enabled=1;
Query OK, 148 rows affected (0.01 sec)
```

**Array columns and their elements can also be updated.** The array values use
the usual square brace syntax, as follows. For the record, array updates are
naturally inplace.

```sql
UPDATE myindex SET arr=[1,2,3,4,5] WHERE id=123
UPDATE myindex SET arr[3]=987 WHERE id=123
```

Element values are also type-checked and range-checked. For example, attempts to
update `INT8` arrays with out-of-bounds integer values must fail.

#### In-place updates

Updates fundamentally fall into two different major categories.

The first one is **in-place** updates that only modify the value but keep
the length intact. (And type too, in the JSON field update case.) Naturally,
all the numeric column updates are like that.

The second one is **non-inplace** updates that need to modify the value length.
Any string or MVA update is like that.

With an in-place update, the new values overwrite the eligible old values
wherever those are stored, and that is as efficient as possible.

**Any fixed-width attributes and any fixed-width JSON fields can be efficiently
updated in-place.**

At the moment, in-place updates are supported for any numeric values (ie. bool,
integer, or float) stored either as attributes or within JSON, for fixed arrays,
and for JSON arrays, ie. optimized `FLOAT` or `INT32` vectors stored in JSON.

**You can use the `UPDATE INPLACE` syntax to *force* an in-place update**, where
applicable. Adding that `INPLACE` keyword *ensures* that the types and widths
are supported, and that the update happens in-place. Otherwise, the update must
fail, while without `INPLACE` it could still attempt (slower) non-inplace path.

This isn't much of an issue when updating simple numeric columns that naturally
*only* support in-place updates, but this does makes a difference when updating
values in JSON. Consider the following two queries.

```
UPDATE myindex SET j.foo=123 WHERE id=1
UPDATE myindex SET j.bar=json('[1,2,3]') WHERE id=1
```

They seem innocuous, but depending on what data is *actually* stored in `foo`
and `bar`, these may not be able to quickly update just the value in-place, and
would need to replace the entire JSON. What if `foo` is a string? What if `bar`
is an array of a matching type but different length? Oops, we can't (quickly!)
change neither the data type nor length in-place, so we need to (slowly!) remove
the old values, and insert the new values, and store the resulting new version
of our JSON somewhere.

And that might not be our intent. We sometimes *require* that certain updates
are carried out either quickly and in-place, or not at all, and `UPDATE INPLACE`
lets us do exactly that.

**Multi-row in-place updates only affect eligible JSON values.** That is, if
some of the JSON values can be updated and some can not, the entire update will
*not* fail, but only the eligible JSON values (those of matching type) will be
updated. See an example just below.

**In-place JSON array updates keep the pre-existing array length.** New arrays
that are too short are zero-padded. New arrays that are too long are truncated.
As follows.

```sql
mysql> select * from rt;
+------+------+-------------------------+
| id   | gid  | j                       |
+------+------+-------------------------+
|    1 |    0 | {"foo":[1,1,1,1]}       |
|    2 |    0 | {"foo":"bar"}           |
|    3 |    0 | {"foo":[1,1,1,1,1,1,1]} |
+------+------+-------------------------+
3 rows in set (0.00 sec)

mysql> update inplace rt set gid=123, j.foo=json('[5,4,3,2,1]') where id<5;
Query OK, 3 rows affected (0.00 sec)

mysql> select * from rt;
+------+------+-------------------------+
| id   | gid  | j                       |
+------+------+-------------------------+
|    1 |  123 | {"foo":[5,4,3,2]}       |
|    2 |  123 | {"foo":"bar"}           |
|    3 |  123 | {"foo":[5,4,3,2,1,0,0]} |
+------+------+-------------------------+
3 rows in set (0.00 sec)
```

As a side note, note that the `gid=123` update part applied even to those rows
where the `j.foo` could not be applied. This is rather intentional, multi-value
updates are not atomic, they may update whatever parts they can.

**Syntax error is raised for unsupported (non-fixed-width) column types.**
`UPDATE INPLACE` fails early on those, at the query parsing stage.

```sql
mysql> UPDATE rt SET str='text' WHERE MATCH('test') AND enabled=1;
Query OK, 148 rows affected (0.01 sec)

mysql> UPDATE INPLACE rt SET str='text' WHERE MATCH('test') AND enabled=1;
ERROR 1064: sphinxql: syntax error, unexpected QUOTED_STRING, expecting
    CONST_INT or CONST_FLOAT or DOT_NUMBER or '-' near ...
```

**Partial JSON updates are now allowed**, ie. you can now update individual
key-value pairs within JSON rather than overwriting the entire JSON.

As of v.3.5 these must be in-place, with the respective limitations, but there's
work underway to support more generic updates.

**Individual JSON array elements can be updated.** Naturally those are in-place.

```sql
mysql> update inplace rt set j.foo[1]=33 where id = 1;
Query OK, 1 rows affected (0.00 sec)

mysql> select * from rt;
+------+------+-------------------------+
| id   | gid  | j                       |
+------+------+-------------------------+
|    1 |  123 | {"foo":[5,33,3,2]}      |
|    2 |  123 | {"foo":"bar"}           |
|    3 |  123 | {"foo":[5,4,3,2,1,0,0]} |
+------+------+-------------------------+
3 rows in set (0.00 sec)
```

**In-place value updates are NOT atomic, dirty single-value reads CAN happen.**
A concurrent reader thread running a `SELECT` may (rather rarely) end up reading
a value that is neither here nor there, and "mixes" the old and new values.

The chances of reading a "mixed" value are naturally (much) higher with larger
arrays that simple numeric values. Imagine that you're updating 128D embeddings
vectors, and that the `UPDATE` thread gets stalled after just a few values while
still working on some row. Concurrent readers then can (and will!) occasionally
read a "mixed" vector for that row at that moment.

How frequently does that actually happen? We tested that with 1M rows and 100D
vectors, write workload that was constantly updating ~15K rows per second, and
read workload that ran selects scanning the entire 1M rows. The "mixed read"
error rate was roughly 1 in ~1M rows, that is, 100 selects reading 1M rows each
would on average report just ~100 "mixed" rows out of the 100M rows processed
total. We deem that an acceptable rate for our applications; of course, your
workload may be different and your mileage may vary.

#### `UPDATE` options

Finally, `UPDATE` supports a few `OPTION` clauses. Namely.

1. `OPTION ignore_nonexistent_columns=1` suppresses any errors when trying to
   update non-existent columns. This may be useful for updates on distributed
   indexes that combine participants with differing schemas. The default is 0.

2. `OPTION strict=1` affects JSON updates. In strict mode, any JSON update
   warnings (eg. in-place update type mismatches) are promoted to hard errors,
   the entire update is cancelled. In non-strict mode, multi-column or multi-key
   updates may apply partially, ie. change column number one but not the JSON
   key number two. The default is 0, but we strongly suggest using 1, because
   the strict mode *will* eventually become either the default or even the only
   option.

```sql
mysql> update inplace rt set j.foo[1]=22 where id > 0 option strict=0;
Query OK, 2 rows affected (0.00 sec)

mysql> select * from rt;
+------+------+--------------------------+
| id   | gid  | j                        |
+------+------+--------------------------+
|    1 |  123 | {"foo":[5,22,3,2]}       |
|    2 |  123 | {"foo":"bar"}            |
|    3 |  123 | {"foo":[5,22,3,2,1,0,0]} |
+------+------+--------------------------+
3 rows in set (0.00 sec)

mysql> update inplace rt set j.foo[1]=33 where id > 0 option strict=1;
ERROR 1064 (42000): index 'rt': document 2, value 'j.foo[1]': can not update (not found)

mysql> select * from rt;
+------+------+--------------------------+
| id   | gid  | j                        |
+------+------+--------------------------+
|    1 |  123 | {"foo":[5,22,3,2]}       |
|    2 |  123 | {"foo":"bar"}            |
|    3 |  123 | {"foo":[5,22,3,2,1,0,0]} |
+------+------+--------------------------+
3 rows in set (0.01 sec)
```


Functions reference
--------------------

This section should eventually contain the complete reference on functions that
are supported in `SELECT` and other applicable places.

If the function you're looking for is not yet documented here, please refer to
the legacy [Sphinx v.2.x reference](sphinx2.html#expressions). Beware that
the legacy reference may not be up to date.

Here's a complete list of built-in Sphinx functions.

  * [ABS](sphinx2.html#expr-func-abs)
  * [ALL](sphinx2.html#expr-func-all)
  * [ANY](sphinx2.html#expr-func-any)
  * [ANNOTS](#annots-function)
  * [ATAN2](sphinx2.html#expr-func-atan2)
  * [BIGINT](sphinx2.html#expr-func-bigint)
  * [BIGINT_SET](#bigint_set-function)
  * [BITCOUNT](#bitcount-function)
  * [BITDOT](sphinx2.html#expr-func-bitdot)
  * [BM25F](#bm25f)
  * [CEIL](sphinx2.html#expr-func-ceil)
  * [COALESCE](#coalesce-function)
  * [CONTAINS](#contains-function)
  * [CONTAINSANY](#containsany-function)
  * [COS](sphinx2.html#expr-func-cos)
  * [CRC32](sphinx2.html#expr-func-crc32)
  * [CURTIME](#curtime-function)
  * [DAY](sphinx2.html#expr-func-day)
  * [DOCUMENT](#document-function)
  * [DOT](#dot-function)
  * [DOUBLE](sphinx2.html#expr-func-double)
  * [DUMP](#dump-function)
  * [EXIST](#exist-function)
  * [EXP](sphinx2.html#expr-func-exp)
  * [FACTORS](#factors-function)
  * [FIBONACCI](sphinx2.html#expr-func-fibonacci)
  * [FLOAT](#float-function)
  * [FLOOR](sphinx2.html#expr-func-floor)
  * [FVEC](#fvec-function)
  * [GEODIST](#geodist-function)
  * [GEOPOLY2D](sphinx2.html#expr-func-geopoly2d)
  * [GREATEST](sphinx2.html#expr-func-greatest)
  * [GROUP_COUNT](#group_count-function)
  * [HOUR](sphinx2.html#expr-func-hour)
  * [IDIV](sphinx2.html#expr-func-idiv)
  * [IF](sphinx2.html#expr-func-if)
  * [IN](sphinx2.html#expr-func-in)
  * [INDEXOF](sphinx2.html#expr-func-indexof)
  * [INTEGER](#integer-function)
  * [INTERSECT_LEN](#intersect_len-function)
  * [INTERVAL](sphinx2.html#expr-func-interval)
  * [L1DIST](#l1dist-function)
  * [LEAST](sphinx2.html#expr-func-least)
  * [LENGTH](sphinx2.html#expr-func-length)
  * [LN](sphinx2.html#expr-func-ln)
  * [LOG10](sphinx2.html#expr-func-log10)
  * [LOG2](sphinx2.html#expr-func-log2)
  * [MAX](sphinx2.html#expr-func-max)
  * [MIN](sphinx2.html#expr-func-min)
  * [MINGEODIST](#mingeodist-function)
  * [MINGEODISTEX](#mingeodistex-function)
  * [MIN_TOP_SORTVAL](sphinx2.html#expr-func-min-top-sortval)
  * [MIN_TOP_WEIGHT](sphinx2.html#expr-func-min-top-weight)
  * [MINUTE](sphinx2.html#expr-func-minute)
  * [MONTH](sphinx2.html#expr-func-month)
  * [NOW](sphinx2.html#expr-func-now)
  * [PACKEDFACTORS](sphinx2.html#expr-func-packedfactors)
  * [POLY2D](sphinx2.html#expr-func-poly2d)
  * [POW](sphinx2.html#expr-func-pow)
  * [PP](#pp-function)
  * [PQMATCHED](#pqmatched-function)
  * [QUERY](#query-function)
  * [RAND](sphinx2.html#expr-func-rand)
  * [REMAP](sphinx2.html#expr-func-remap)
  * [SECOND](sphinx2.html#expr-func-second)
  * [SIN](sphinx2.html#expr-func-sin)
  * [SINT](sphinx2.html#expr-func-sint)
  * [SLICEAVG](#slice-functions)
  * [SLICEMAX](#slice-functions)
  * [SLICEMIN](#slice-functions)
  * [SQRT](sphinx2.html#expr-func-sqrt)
  * [STRPOS](#strpos-function)
  * [TIMEDIFF](#timediff-function)
  * [UINT](#uint-function)
  * [UTC_TIME](#utc_time-function)
  * [UTC_TIMESTAMP](#utc_timestamp-function)
  * [WORDPAIRCTR](#wordpairctr-function)
  * [YEAR](sphinx2.html#expr-func-year)
  * [YEARMONTH](sphinx2.html#expr-func-yearmonth)
  * [YEARMONTHDAY](sphinx2.html#expr-func-yearmonthday)
  * [ZONESPANLIST](#zonespanlist-function)


### `ANNOTS()` function

```sql
ANNOTS()
ANNOTS(<json_array>)
```

`ANNOTS()` returns the individual matched annotations. In the no-argument form,
it returns a list of annotations indexes matched in the field (the "numbers" of
the matched "lines" within the field). In the 1-argument form, it slices a given
JSON array using that index list, and returns the slice.

For details, refer either to [annotations docs](#using-annotations) in general,
or the ["Accessing matched annotations" article](#accessing-matched-annotations)
specifically.


### `BIGINT_SET()` function

```sql
BIGINT_SET(const_int1 [, const_int2, ...]])
```

`BIGINT_SET()` is a helper function that creates a constant `BIGINT_SET` value.
As of v.3.5, it is only required for `INTERSECT_LEN()`.


### `BITCOUNT()` function

```sql
BITCOUNT(int_expr)
```

`BITCOUNT()` returns the number of bits set to 1 in its argument. The argument
must evaluate to any integer type, ie. either `UINT` or `BIGINT` type. This is
useful for processing various bit masks on Sphinx side.


### `COALESCE()` function

```sql
COALESCE(json.key, numeric_expr)
```

`COALESCE()` function returns either the first argument if it is not `NULL`, or
the second argument otherwise.

As pretty much everything except JSON is not nullable in Sphinx, the first
argument must be a JSON key.

The second argument is currently limited to numeric types. Moreover, at the
moment `COALESCE()` always returns `float` typed result, thus forcibly casting
whatever argument it returns to float. Beware that this looses precision when
returning bigger integer values from either argument!

The second argument does *not* need to be a constant. An arbitrary expression is
allowed.

Examples:
```sql
mysql> select coalesce(j.existing, 123) val
    -> from test1 where id=1;
+-----------+
| val       |
+-----------+
| 1107024.0 |
+-----------+
1 row in set (0.00 sec)

mysql> select coalesce(j.missing, 123) val
    -> from test1 where id=1;
+-------+
| val   |
+-------+
| 123.0 |
+-------+
1 row in set (0.00 sec)

mysql> select coalesce(j.missing, 16777217) val
    -> from test1 where id=1;
+------------+
| val        |
+------------+
| 16777216.0 |
+------------+
1 row in set (0.00 sec)

mysql> select coalesce(j.missing, sin(id)+3) val from lj where id=1;
+------------+
| val        |
+------------+
| 3.84147096 |
+------------+
1 row in set (0.00 sec)
```


### `CONTAINS()` function

```sql
CONTAINSANY(POLY2D(...), x, y)
CONTAINSANY(GEOPOLY2D(...), lat, lon)
```

`CONTAINS()` function checks whether its argument point (defined by the 2nd and
3rd arguments) lies within the given polygon, and returns 1 if it does, or 0
otherwise.

Two types of polygons are supported, regular "plain" 2D polygons (that are just
checked against the point as is), and special "geo" polygons (that might require
further processing).

In the `POLY2D()` case there are no restrictions on the input data, both
polygons and points are just "pure" 2D objects. Naturally you must use the same
units and axis order, but that's it.

With regards to geosearches, you can use `POLY2D()` for "small" polygons with
sides up to 500 km (aka 300 miles). According to our tests, the Earth curvature
introduces a relative error of just 0.03% at that lengths, meaning that results
might be off by just 3 meters (or less) for polygons with sides up to 10 km.

Keep an eye out how this error only applies to sides, to individual segments.
Even if you have a really huge polygon (say over 3000 km in diameter) but built
with small enough segments (say under 10 km each), the "in or out" error will
*still* be under just 3 meters for the entire huge polygon!

When in doubt and/or dealing with huge distances, you should use `GEOPOLY2D()`
which checks every segment length against the 500 km threshold, and tessellates
(splits) too large segments in smaller parts, properly accounting for the Earth
curvature.

Small-sided polygons must pass through `GEOPOLY2D()` unchanged and must produce
exactly the same result as `POLY2D()` would. There's a tiny overhead for the
length check itself, of course, but in most all cases it's a negligible one.


### `CONTAINSANY()` function

```sql
CONTAINSANY(POLY2D(...), json.key)
CONTAINSANY(GEOPOLY2D(...), json.key)
```

`CONTAINSANY()` checks if a 2D polygon specified in the 1st argument contains
any of the 2D points stored in the 2nd argument.

The 2nd argument must be a JSON array of 2D coordinate pairs, that is, an even
number of float values. They must be in the same order and units as the polygon.

So with `POLY2D()` you can choose whatever units (and even axes order), just
ensure you use the same units (and axes) in both your polygon and JSON data.

However, with `GEOPOLY2D()` you **must** keep all your data in the (lat,lon)
order, you **must** use degrees, and you **must** use the properly normalized
ranges (-90 to 90 for latitudes and -180 to 180 for longitudes respectively),
because that's what `GEOPOLY2D()` expects and emits. All your `GEOPOLY2D()`
arguments and your JSON data must be in that format: degrees, lat/lon order,
normalized.

Examples:
```sql
mysql> select j, containsany(poly2d(0,0, 0,1, 1,1, 1,0), j.points) q from test;
+------------------------------+------+
| j                            | q    |
+------------------------------+------+
| {"points":[0.3,0.5]}         |    1 |
| {"points":[0.4,1.7]}         |    0 |
| {"points":[0.3,0.5,0.4,1.7]} |    1 |
+------------------------------+------+
3 rows in set (0.00 sec)
```


### `CURTIME()` function

```sql
CURTIME()
```

`CURTIME()` returns the current server time, in server time zone, as a string in
`HH:MM:SS` format. It was added for better MySQL connector compatibility.


### `DOCUMENT()` function

```sql
DOCUMENT([{field1 [, field2, ...]]}])
```

`DOCUMENT()` is a helper function that retrieves full-text document fields from
docstore, and returns those as an field-to-content map that can then be passed
to other built-in functions. It naturally requires docstore, and its only usage
is now limited to passing it to `SNIPPET()` calls, as follows.

```sql
SELECT id, SNIPPET(DOCUMENT(), QUERY())
FROM test WHERE MATCH('hello world')

SELECT id, SNIPPET(DOCUMENT({title,body}), QUERY())
FROM test WHERE MATCH('hello world')
```

Without arguments, it fetches all the stored full-text fields. In the 1-argument
form, it expects a list of fields, and fetches just the specified ones.

Refer to the [DocStore documentation section](#using-docstore) for more details.


### `DOT()` function

```sql
DOT(vector1, vector2)
vector = {json.key | array_attr | FVEC(...)}
```

`DOT()` function computes a dot product over two vector arguments.

Vectors can be taken either from JSON, or from array attributes, or specified
as constants using `FVEC()` function. All combinations should generally work.

The result type is always `FLOAT` for consistency and simplicity. (According
to our benchmarks, performance gain from using `UINT` or `BIGINT` for the result
type, where applicable, is pretty much nonexistent anyway.)

Note that *internal* calculations are optimized for specific input argument
types anyway. For instance, `int8` vs `int8` vectors should be quite noticeably
faster than `float` by `double` vectors containing the same data, both because
integer multiplication is less expensive, and because `int8` would utilize 6x
less memory.

So as a rule of thumb, use the narrowest possible type, that yields both better
RAM use and better performance.

When one of the arguments is either NULL, or not a numeric vector (that can very
well happen with JSON), or when both arguments are vectors of different sizes,
`DOT()` returns 0.

On Intel, we have SIMD optimized codepaths that automatically engage where
possible. So for best performance, use SIMD-friendly vector dimensions (that
means multiples of at least 16 bytes in all cases, multiples of 32 bytes on AVX2
CPUs, etc).


### `DUMP()` function

```sql
DUMP(json[.key])
```

`DUMP()` formats JSON (either the entire field or a given key) with additional
internal type information.

This is a semi-internal function, intended for manual troubleshooting only.
Hence, its output format is *not* well-formed JSON, it may (and will) change
arbitrarily, and you must *not* rely on that format anyhow.

That said, `PP()` function still works with `DUMP()` anyway, and pretty-prints
the default compact output of that format, too.

```sql
mysql> SELECT id, j, PP(DUMP(j)) FROM rt \G
*************************** 1. row ***************************
         id: 123
          j: {"foo":"bar","test":1.23}
pp(dump(j)): (root){
  "foo": (string)"bar",
  "test": (double)1.23
}
1 row in set (0.00 sec)
```


### `EXIST()` function

```sql
EXIST('attr_name', default_value)
```

`EXIST()` lets you substitute non-existing numeric columns with a default value.
That may be handy when searching through several indexes with different schemas.

It returns either the column value in those indexes that have the column, or
the default value in those that do not. So it's rather useless for single-index
searches.

The first argument must be a quoted string with a column name. The second one
must be a numeric default value (either integer or float). When the column does
exist, it must also be of a matching type.

```sql
SELECT id, EXIST(v2intcol, 0) FROM indexv1, indexv2
```


### `FACTORS()` function

```sql
FACTORS(['alt_keywords'], [{option=value [, option2=value2, ...]}])
FACTORS(...)[.key[.key[...]]]
```

`FACTORS()` provides both SQL statemetns and UDFs with access to the dynamic
text ranking factors (aka signals) that Sphinx expression ranker computes. This
function is key to advanced ranking implementation.

Internally in the engine the signals are stored in an efficient binary format,
one signals blob per match. `FACTORS()` is essentially an accessor to those.

When used directly, ie. in a `SELECT FACTORS(...)` statement, the signals blob
simply gets formatted as a JSON string.

However, when `FACTORS()` is passed to an UDF, the UDF receives a special
`SPH_UDF_TYPE_FACTORS` type with an efficient direct access API instead. Very
definitely not a string, as that would obliterate the performance. See the
["Using FACTORS() in UDFs"](#using-factors-in-udfs) section for details.

Now, in its simplest form you can simply invoke `FACTORS()` and get all
the signals. But as the syntax spec suggests, there's more than just that.

  * `FACTORS()` can take a string argument with alternative keywords, and rank
     matches against those *arbitrary* keywords rather than the original query
     from `MATCH()`. Moreover, in that form `FACTORS()` works even with non-text
     queries. Refer to ["Ranking: using different keywords..."](#xfactors)
     section for more details on that.

  * `FACTORS()` can take an options map argument that fine-tunes the ranking
    behavior. As of v.3.5, it supports the following two performance flags.

      - `no_atc=1` disables the `atc` signal evaluation.
      - `no_decay=1` disables the `phrase_decayXX` signals evaluation.

  * `FACTORS()` with a key path suffix (aka subscript) can access individual
    signals, and return the respective numeric values (typed `UINT` or `FLOAT`).
    This is primarily intended to simplify researching or debugging individual
    signals, as the full `FACTORS()` output can get pretty large.

Examples!

```sql
# alt keywords
SELECT id, FACTORS('here there be alternative keywords')
FROM test WHERE MATCH('hello world')

# max perf options
SELECT id, FACTORS({no_act=1, no_decay=1}
FROM test WHERE MATCH('hello world')

# single field signal access, via name
SELECT id, FACTORS().factors().fields.title.wlccs
FROM test WHERE MATCH('hello world')

# single field signal access, via number
SELECT id, FACTORS().factors().fields[2].wlccs
FROM test WHERE MATCH('hello world')

# everything everywhere all at once
SELECT id, FACTORS('terra incognita', {no_atc=1}).fields.title.atc
FROM test WHERE MATCH('hello world')
```

`FACTORS()` requires an expression ranker, and auto-switches to that ranker
(even with the proper default expression), unless there was an explicit ranker
specified.

JSON output from `FACTORS()` defaults to compact format, and you can use
`PP(FACTORS())` to pretty-print that.

As a side note, in the distributed search case agents send the signals blobs in
the binary format, for performance reasons.

Specific signal names to use with the `FACTORS().xxx` subscript syntax can be
found in the table in ["Ranking: factors"](#ranking-factors). Subscripts should
be able to access most of what the `ranker=expr('...')` expression can access,
except for the parametrized signals such as `bm25()`. Namely!

  1. All document-level signals, such as `FACTORS().bm15`, etc.
  2. Two query-level signals, `FACTORS().query_tokclass_mask` and
     `FACTORS().query_word_count`.
  3. Most field-level signals, such as `FACTORS().fields[0].has_digit_hits`,
     `FACTORS().fields.title.phrase_decay10`, etc.

Fields must be accessed via `.fields` subscript, and after that, either via
their names as in `FACTORS().fields.title.phrase_decay10` example, or via their
indexes as in `FACTORS().fields[0].has_digit_hits` example. The indexes match
the declaration and the order you get out of the `DESCRIBE` statement.

Last but not least, `FACTORS()` works okay with subselects, and that enables
[two-stage ranking](#ranking-two-stage-ranking), ie. using a faster ranking
model for all the matches, then reranking the top-N results using a slower but
better model. More details in the respective section.


### `FLOAT()` function

```sql
FLOAT(arg)
```

This function converts its argument to `FLOAT` type, ie. 32-bit floating point
value.


### `FVEC()` function

```sql
FVEC(const1 [, const2, ...])
FVEC(json.key)
```

`FVEC()` function lets you define a vector of floats. Two current usecases are:

  * to define a constant vector for subsequent use with [`DOT()`](#dot-function)
  * to pass optimized float vectors stored in JSON to UDFs

**Constant vector form.**

In the first form, the arguments are a list of numeric constants. And note that
there *can* be a difference whether we use integers or floats here!

When both arguments to `DOT()` are integer vectors, `DOT()` can use an optimized
integer implementation, and to define such a vector using `FVEC()`, you should
only use integers.

The rule of thumb with vectors generally is: just use the narrowest possible
type. Because that way, extra optimizations just might kick in. And the other
way, they very definitely will not.

For instance, the optimizer is allowed to widen `FVEC(1,2,3,4)` from integers
to floats alright, no surprise there. Now, in *this* case it is also allowed to
narrow the resulting `float` vector back to integers where applicable, because
we can know that all the *original* values were integers before widening.

And narrowing down from the floating point form like `FVEC(1.0, 2.0, 3.0, 4.0)`
to integers is strictly prohibited. So even though the values actually are
the same, in the first case additional integer-only optimizations can be used,
and in the second case they can't.

**UDF argument wrapper form.**

In the second form, the only argument must be a JSON key, and the output is only
intended for UDF functions (because otherwise this `FVEC()` wrapper should not
be needed and you would just use the key itself). The associated value type gets
checked, optimized float vectors get wrapped and passed to UDF, and any other
types are replaced with a null vector (zero length and no data pointer) in
the UDF call. The respective UDF type is `SPH_UDF_TYPE_FLOAT_VEC`.

Note that this case is intentionally designed as a fast accessor for UDFs that
just passes `float` vectors to them, and avoids any data copying and conversion.

So if you attempt to wrap and pass anything else, null vector will be passed to
the UDF. Could be a generic mixed vector with numeric values of different types,
could be an optimized `int8` vector, could be a `double` vector - but in all
these cases, despite the fact that they are compatible and *could* technically
be converted to some temporary `float` vector and then passed down, that kind
of a conversion just does not happen. Intentionally, for performance reasons.


### `GEODIST()` function

```sql
GEODIST(lat1, lon1, lat2, lon2, [{opt=value, [ ...]}])
```

`GEODIST()` computes geosphere distance between two given points specified by
their coordinates.

**The default units are radians and meters.** In other words, by default input
latitudes and longitudes are treated as radians, and the output distance is in
meters. You can change all that using the 4th options map argument, see below.

**We now strongly suggest using explicit `{in=rad}` instead of the defaults.**
Because radians by default were a bad choice and we plan to change that default.

**Constant vs attribute lat/lon (and other cases) are optimized.** You can put
completely arbitrary expressions in any of the four inputs, and `GEODIST()` will
honestly compute those, no surprise there. But the most common cases (notably,
the constant lat/lon pair vs the float lat/lon attribute pair) are internally
optimized, and they execute faster. For one, you really should *not* convert
between radians and degrees manually, and use the in/out options instead.

```sql
-- slow, manual, and never indexed
SELECT id, GEODIST(lat*3.141592/180, lon*3.141592/180,
  30.0*3.141592/180, 60.0*3.141592/180) ...

-- fast, automatic, and can use indexes
SELECT id, GEODIST(lat, lon, 30.0, 60.0, {in=deg})
```

**Options map lets you specify units and the calculation method (formula).**
Here is the list of known options and their values:

  * `in = {deg | degrees | rad | radians}`, specifies the input units;
  * `out = {m | meters | km | kilometers | ft | feet | mi | miles}`, specifies
     the output units;
  * `method = {haversine | adaptive}`, specifies the geodistance calculation
    method.

The current defaults are `{in=rad, out=m, method=adaptive}` but, to reiterate,
we now plan to eventually change to `{in=deg}`, and therefore strongly suggest
putting explicit `{in=rad}` in your queries.

`{method=adaptive}` is our current default, well-optimized implementation that
is both more precise and (much) faster than `haversine` at all times.

`{method=haversine}` is the industry-standard method that was our default (and
only implementation) before, and is still included, because why not.


### `GROUP_COUNT()` function

```sql
GROUP_COUNT(int_col, no_group_value)
```

Very basically, `GROUP_COUNT()` quickly computes per-group counts, without
the full grouping.

Bit more formally, `GROUP_COUNT()` computes an element count for a group of
matched documents defined by a specific `int_col` column value. Except when
`int_col` value equals `no_group_value`, in which case it returns 1.

First argument must be a `UINT` or `BIGINT` column (more details below). Second
argument must be a constant.

`GROUP_COUNT()` value for all documents where `int_col != no_group_value`
condition is true must be *exactly* what `SELECT COUNT(*) .. GROUP BY int_col`
would have computed, just without the actual grouping. Key differences between
`GROUP_COUNT()` and "regular" `GROUP BY` queries are:

  1. **No actual grouping occurs.** For example, if a query matches 7 documents
     with `user_id=123`, all these documents will be included in the result set,
     and `GROUP_COUNT(user_id,0)` will return 7.

  2. **Documents "without" a group are considered unique.** Documents with
     `no_group_value` in the `int_col` column are **intentionally** considered
     unique entries, and `GROUP_COUNT()` must return 1 for those documents.

  3. **Better performance.** Avoiding the actual grouping and skipping any work
     for "unique" documents where `int_col = no_group_value` means that we can
     compute `GROUP_COUNT()` somewhat faster.

Naturally, `GROUP_COUNT()` result can not be available until we scan through all
the matches. So you can *not* use it in `GROUP BY`, `ORDER BY`, `WHERE`, or any
other clause that gets evaluated "earlier" on a per-match basis.

Beware that using this function anyhow else than simply SELECT-ing its value
is *not* supported. Queries that do anything else *should* fail with an error.
If they do not, the results will be undefined.

At the moment, first argument *must* be a column, and the column type *must* be
integer, ie. UINT or BIGINT. That is, it may refer either to an index attribute,
or to an aliased expression. Directly doing a `GROUP_COUNT()` over an expression
is not supported yet. Note that JSON key accesses are also expressions. So for
instance:

```sql
SELECT GROUP_COUNT(x, 0) FROM test; # ok
SELECT y + 1 as gid, GROUP_COUNT(gid, 0) FROM test; # ok
SELECT UINT(json.foo) as gid, GROUP_COUNT(gid, 0) FROM test; # ok

SELECT GROUP_COUNT(1 + user_id, 0) FROM test; # error!
```

Here's an example that should exemplify the difference between `GROUP_COUNT()`
and regular `GROUP BY` queries.

```sql
mysql> select *, count(*) from rt group by x;
+------+------+----------+
| id   | x    | count(*) |
+------+------+----------+
|    1 |   10 |        2 |
|    2 |   20 |        2 |
|    3 |   30 |        3 |
+------+------+----------+
3 rows in set (0.00 sec)

mysql> select *, group_count(x,0) gc from rt;
+------+------+------+
| id   | x    | gc   |
+------+------+------+
|    1 |   10 |    2 |
|    2 |   20 |    2 |
|    3 |   30 |    3 |
|    4 |   20 |    2 |
|    5 |   10 |    2 |
|    6 |   30 |    3 |
|    7 |   30 |    3 |
+------+------+------+
7 rows in set (0.00 sec)
```

We expect `GROUP_COUNT()` to be particularly useful for "sparse" grouping, ie.
when the vast majority of documents are unique (not a part of any group), but
there also are a few occasional groups of documents here and there. For example,
what if you have 990K unique documents with `gid=0`, and 10K more documents
divided into various non-zero `gid` groups. In order to identify such groups in
your SERP, you could `GROUP BY` on something like `IF(gid=0,id,gid)`, or you
could just use `GROUP_COUNT(gid,0)` instead. Compared to `GROUP BY`, the latter
does *not* fold the occasional non-zero `gid` groups into a single result set
row. But it works much, much faster.


### `INTEGER()` function

```sql
INTEGER(arg)
```

**THIS IS A DEPRECATED FUNCTION SLATED FOR REMOVAL. USE UINT() INSTEAD.**

This function converts its argument to `UINT` type, ie. 32-bit unsigned integer.


### `INTERSECT_LEN()` function

```sql
INTERSECT_LEN(<mva_column>, BIGINT_SET(...))
```

This function returns the number of common values found both in an MVA column,
and a given constant values set. Or in other words, the number of intersections
between the two. This is useful when you need to compute the number of
the matching tags count on Sphinx side.

The first argument can be either `UINT_SET` or `BIGINT_SET` column. The second
argument should be a constant `BIGINT_SET()`.

```sql
mysql> select id, mva,
    -> intersect_len(mva, bigint_set(20, -100)) n1,
    -> intersect_len(mva, bigint_set(-200)) n2 from test;
+------+----------------+------+------+
| id   | mva            | n1   | n2   |
+------+----------------+------+------+
|    1 | -100,-50,20,70 |    2 |    0 |
|    2 | -350,-200,-100 |    1 |    1 |
+------+----------------+------+------+
2 rows in set (0.00 sec)
```


### `L1DIST()` function

```sql
L1DIST(array_attr, FVEC(...))
```

`L1DIST()` function computes a L1 distance (aka Manhattan or grid distance) over
two vector arguments. This is really just a sum of absolute differences,
`sum(abs(a[i] - b[i]))`.

Input types are currently limited to array attributes vs constant vectors.

The result type is always `FLOAT` for consistency and simplicity.

On Intel, we have SIMD optimized codepaths that automatically engage where
possible. So for best performance, use SIMD-friendly vector dimensions (that
means multiples of at least 16 bytes in all cases, multiples of 32 bytes on AVX2
CPUs, etc).


### `MINGEODIST()` function

```sql
MINGEODIST(json.key, lat, lon, [{opt=value, [ ...]}])
```

`MINGEODIST()` computes a minimum geodistance between the (lat,lon) anchor point
and all the points stored in the specified JSON key.

The 1st argument must be a JSON array of (lat,lon) coordinate pairs, that is,
contain an even number of proper float values. The 2nd and 3rd arguments must
also be floats.

The optional 4th argument is an options map, exactly as in the single-point
`GEODIST()` function.

Example!

```sql
MINGEODIST(j.coords, 37.8087, -122.41, {in=deg, out=mi})
```

That computes the minimum geodistance (in miles) from Pier 39 (because degrees)
to any of the points stored in `j.coords` array.

Note that queries with a `MINGEODIST()` condition can benefit from a `MULTIGEO`
index on the respective JSON field. See the [Geosearch](#searching-geosearches)
section for details.


### `MINGEODISTEX()` function

```sql
MINGEODISTEX(json.key, lat, lon, [{opt=value, [ ...]}])
```

`MINGEODISTEX()` works exactly as `MINGEODIST()`, but it returns an extended
"pair" result comprised of *both* the minimum geodistance *and* the respective
closest geopoint index within the `json.key` array. (Beware that for acccess to
values back in `json.key` you have to scale that index by 2, because they are
pairs! See the examples just below.)

In the final result set, you get a `<distance>, <index>` string (instead of only
the `<distance>` value that you get from `MINGEODIST()`), like so.

```sql
mysql> SELECT MINGEODISTEX(j.coords, 37.8087, -122.41,
    -> {in=deg, out=mi}) d FROM test1;
+--------------+
| d            |
+--------------+
| 1.0110466, 3 |
+--------------+
1 row in set (0.00 sec)

mysql> SELECT MINGEODIST(j.coords, 37.8087, -122.41,
    -> {in=deg, out=mi}) d FROM test1;
+-----------+
| d         |
+-----------+
| 1.0110466 |
+-----------+
1 row in set (0.00 sec)
```

So the minimum distance (from Pier 39 again) in this example is 1.0110466 miles,
and in addition we have that the closest geopoint in `j.coords` is lat-lon pair
number 3.

So its latitude must be.. right, latitude is at `j.coords[6]` and longitude at
`j.coords[7]`, respectively. Geopoint is a *pair* of coordinates, so we have to
scale by 2 to convert from *geopoint* indexes to individual value indexes. Let's
check that.

```sql
mysql> SELECT GEODIST(j.coords[6], j.coords[7], 37.8087, -122.41,
    -> {in=deg,out=mi}) d FROM test1;
+-----------+
| d         |
+-----------+
| 1.0110466 |
+-----------+
1 row in set (0.00 sec)

mysql> SELECT j.coords FROM test1;
+-------------------------------------------------------------------------+
| j.coords                                                                |
+-------------------------------------------------------------------------+
| [37.8262,-122.4222,37.82,-122.4786,37.7764,-122.4347,37.7952,-122.4028] |
+-------------------------------------------------------------------------+
1 row in set (0.00 sec)
```

Well, looks legit.

But what happens if you filter or sort by that "pair" value? Short answer, it's
going to pretend that it's just distance.

Longer answer, it's designed to behave exactly as `MINGEODIST()` does in those
contexts, so in `WHERE` and `ORDER BY` clauses the `MINGEODISTEX()` pair gets
reduced to its first component, and that's our minimum distance.

```sql
mysql> SELECT MINGEODISTEX(j.coords, 37.8087, -122.41,
    -> {in=deg, out=mi}) d from test1 WHERE d < 2.0;
+--------------+
| d            |
+--------------+
| 1.0110466, 3 |
+--------------+
1 row in set (0.00 sec)
```

Well, 1.011 miles is indeed less than 2.0 miles, still legit. (And yes, those
extra 2.953 inches that we have here over 1.011 miles do sooo *extremely* annoy
my inner Sheldon, but what can one do.)


### `PP()` function

```sql
PP(json[.key])
PP(DUMP(json.key))
PP(FACTORS())
```

`PP()` function pretty-prints JSON output (which by default would be compact
rather than prettified). It can be used either with JSON columns (and fields),
or with `FACTORS()` function. For example:

```sql
mysql> select id, j from lj limit 1 \G
*************************** 1. row ***************************
id: 1
 j: {"gid":1107024, "urlcrc":2557061282}
1 row in set (0.01 sec)

mysql> select id, pp(j) from lj limit 1 \G
*************************** 1. row ***************************
   id: 1
pp(j): {
  "gid": 1107024,
  "urlcrc": 2557061282
}
1 row in set (0.01 sec)

mysql> select id, factors() from lj where match('hello world')
    -> limit 1 option ranker=expr('1') \G
*************************** 1. row ***************************
       id: 5332
factors(): {"bm15":735, "bm25a":0.898329, "field_mask":2, ...}
1 row in set (0.00 sec)

mysql> select id, pp(factors()) from lj where match('hello world')
    -> limit 1 option ranker=expr('1') \G
*************************** 1. row ***************************
       id: 5332
pp(factors()): {
  "bm15": 735,
  "bm25a": 0.898329,
  "field_mask": 2,
  "doc_word_count": 2,
  "fields": [
    {
      "field": 1,
      "lcs": 2,
      "hit_count": 2,
      "word_count": 2,
      ...
1 row in set (0.00 sec)
```


### `PQMATCHED()` function

```sql
PQMATCHED()
```

`PQMATCHED()` returns a comma-separated list of `DOCS()` ids that were matched
by the respective stored query. It only works in percolate indexes and requires
`PQMATCH()` searches. For example.

```sql
mysql> SELECT PQMATCHED(), id FROM pqtest
    -> WHERE PQMATCH(DOCS({1, 'one'}, {2, 'two'}, {3, 'three'}));
+-------------+-----+
| pqmatched() | id  |
+-------------+-----+
| 1,2,3       | 123 |
| 2           | 124 |
+-------------+-----+
2 rows in set (0.00 sec)
```

For more details, refer to the [percolate queries](#searching-percolate-queries)
section.


### `QUERY()` function

```sql
QUERY()
```

`QUERY()` is a helper function that returns the current full-text query, as is.
Originally intended as a syntax sugar for `SNIPPET()` calls, to avoid repeating
the keywords twice, but may also be handy when generating ML training data.

```sql
mysql> select id, weight(), query() from lj where match('Test It') limit 3;
+------+-----------+---------+
| id   | weight()  | query() |
+------+-----------+---------+
| 2709 | 24305.277 | Test It |
| 2702 | 24212.217 | Test It |
| 8888 | 24212.217 | Test It |
+------+-----------+---------+
3 rows in set (0.00 sec)
```


### Slice functions

```sql
SLICEAVG(json.key, min_index, sup_index)
SLICEMAX(json.key, min_index, sup_index)
SLICEMIN(json.key, min_index, sup_index)
```

| Function call example      | Info                              |
|----------------------------|-----------------------------------|
| `SLICEAVG(j.prices, 3, 7)` | Computes average value in a slice |
| `SLICEMAX(j.prices, 3, 7)` | Computes minimum value in a slice |
| `SLICEMIN(j.prices, 3, 7)` | Computes maximum value in a slice |

Slice functions (`SLICEAVG`, `SLICEMAX`, and `SLICEMIN`) expect a JSON array
as their 1st argument, and two constant integer indexes A and B as their 2nd and
3rd arguments, respectively. Then they compute an aggregate value over the array
elements in the respective slice, that is, from index A inclusive to index B
exclusive (just like in Python and Golang). For instance, in the example above
elements 3, 4, 5, and 6 will be processed, but not element 7. The indexes are,
of course, 0-based.

The returned value is `float`, even when all the input values are actually
integer.

Non-arrays and slices with non-numeric items will return a value of `0.0`
(subject to change to `NULL` eventually).


### `STRPOS()` function

```sql
STRPOS(haystack, const_needle)
```

`STRPOS()` returns the index of the first occurrence of its second argument
("needle") in its first argument ("haystack"), or `-1` if there are no
occurrences.

The index is counted in bytes (rather that Unicode codepoints).

At the moment, needle must be a constant string. If needle is an empty string,
then 0 will be returned.


### `TIMEDIFF()` function

```sql
TIMEDIFF(timestamp1, timestamp2)
```

`TIMEDIFF()` takes 2 integer timestamps, and returns the difference between them
in a `HH:MM:SS` format. It was added for better MySQL connector compatibility.


### `UINT()` function

```sql
UINT(arg)
```

This function converts its argument to `UINT` type, ie. 32-bit unsigned integer.


### `UTC_TIME()` function

```sql
UTC_TIME()
```

`UTC_TIME()` returns the current server time, in UTC time zone, as a string in
`HH:MM:SS` format. It was added for better MySQL connector compatibility.


### `UTC_TIMESTAMP()` function

```sql
UTC_TIMESTAMP()
```

`UTC_TIMESTAMP()` returns the current server time, in UTC time zone, as a string
in `YYYY-MM-DD HH:MM:SS` format. It was added for better MySQL connector
compatibility.


### `WORDPAIRCTR()` function

```sql
WORDPAIRCTR('field', 'bag of keywords')
```

`WORDPAIRCTR()` returns the word pairs CTR computed for a given field (which
must be with tokhashes) and a given "replacement query", an arbitrary bag of
keywords.

Auto-converts to a constant 0 when there are no eligible "query" keywords, ie.
no keywords that were mentioned in the settings file. Otherwise computes just as
`wordpair_ctr` signal, ie. returns -1 when the total "views" are strictly under
the threshold, or "clicks" to "views" ratio otherwise.

For more info on how specifically the values are calculated, see the
["Ranking: tokhashes..."](#ranking-tokhashes-and-wordpair_ctr) section.


### `ZONESPANLIST()` function

```sql
ZONESPANLIST()
```

`ZONESPANLIST()` returns the list of all the spans matched by a `ZONESPAN`
operator in a simple text format. Each matching (contiguous) span is encoded
with a `query_span_id:doc_span_seq` pair of numbers, and all such pairs are then
joined into a space separated string.


Server variables reference
---------------------------

`searchd` has a number of server variables that can be changed on the fly using
the `SET GLOBAL var = value` statement. Note how some of these are runtime only,
and will revert to the default values on every `searchd` restart. Others may be
also set via the config file, and will revert to those config values on restart.
This section provides a reference on all those variables.

  * [`agent_connect_timeout`](#agent-connection-variables)
  * [`agent_query_timeout`](#agent-connection-variables)
  * [`agent_retry_count`](#agent-connection-variables)
  * [`agent_retry_delay`](#agent-connection-variables)
  * [`attrindex_thresh`](#attrindex_thresh-variable)
  * [`client_timeout`](#client_timeout-variable)
  * [`cpu_stats`](#cpu_stats-variable)
  * [`ha_period_karma`](#ha_period_karma-variable)
  * [`ha_ping_interval`](#ha_ping_interval-variable)
  * [`ha_weight`](#ha_weight-variable)
  * [`log_debug_filter`](#log_debug_filter-variable)
  * [`log_level`](#log_level-variable)
  * [`max_filters`](#max_filters-variable)
  * [`max_filter_values`](#max_filter_values-variable)
  * [`net_spin_msec`](#net_spin_msec-variable)
  * [`qcache_max_bytes`](sphinx2.html#qcache)
  * [`qcache_thresh_msec`](sphinx2.html#qcache)
  * [`qcache_ttl_sec`](sphinx2.html#qcache)
  * [`query_log_min_msec`](#query_log_min_msec-variable)
  * [`read_timeout`](#read_timeout-variable)
  * [`siege`](#siege-mode)
  * [`siege_max_fetched_docs`](#siege-mode)
  * [`sphinxql_timeout`](#sphinxql_timeout-variable)
  * [`sql_fail_filter`](#sql_fail_filter-variable)
  * [`sql_log_file`](#sql_log_file-variable)


### Agent connection variables

```sql
SET GLOBAL agent_connect_timeout = 100
SET GLOBAL agent_query_timeout = 3000
SET GLOBAL agent_retry_count = 2
SET GLOBAL agent_retry_delay = 50
```

Network connections to agents (remote `searchd` instances) come with several
associated timeout and retry settings. Those can be adjusted either in config on
per-index level, or even in `SELECT` on per-query level. However, in absence of
any explicit per-index or per-query settings, the global per-server settings
take effect. Which can, too, be adjusted on the fly.

The specific settings and their defaults are as follows.

  * `agent_connect_timeout` is the connect timeout, in msec. Defaults to 1000.
  * `agent_query_timeout` is the query timeout, in msec. Defaults to 3000.
  * `agent_retry_count` is the number of retries to make. Defaults to 0.
  * `agent_retry_delay` is the delay between retries, in msec. Defaults to 500.


### `attrindex_thresh` variable

```sql
SET GLOBAL attrindex_thresh = 256
```

Minimum segment size required to enable building the
[attribute indexes](#using-attribute-indexes), counted in rows. Default is 1024.

Sphinx will only create attribute indexes for "large enough" segments (be those
RAM or disk segments). As a corollary, if the entire FT index is small enough,
ie. under this threshold, attribute indexes will not be engaged at all.

At the moment, this setting seem useful for testing and debugging only, and
normally you must not need to tweak it in production.


### `client_timeout` variable

```sql
SET GLOBAL client_timeout = 15
```

Sets the allowed timeout between requests for SphinxAPI clients using persistent
connections. Counted in sec, default is 300, or 5 minutes.

See also [`read_timeout`](#read_timeout-variable) and
[`sphinxql_timeout`](#sphinxql_timeout-variable).


### `cpu_stats` variable

```sql
SET GLOBAL cpu_stats = {0 | 1}
```

Whether to compute and return actual CPU time (rather than wall time) stats.
Boolean, default is 0. Can be also set to 1 by `--cpustats` CLI switch.


### `ha_period_karma` variable

```sql
SET GLOBAL ha_period_karma = 120
```

Sets the size of the time window used to pick a specific HA agent. Counted in
sec, default is 60, or 1 minute.


### `ha_ping_interval` variable

```sql
SET GLOBAL ha_ping_interval = 500
```

Sets the delay between the periodic HA agent pings. Counted in msec, default is
1000, or 1 second.


### `ha_weight` variable

```sql
SET GLOBAL ha_weight = 80
```

Sets the balancing weight for the host. Used with weighted round-robin strategy.
This is a percentage, so naturally it must be in the 0 to 100 range.

The default weight is 100, meaning "full load" (as determined by the balancer
node). The minimum weight is 0, meaning "no load", ie. the balancer should not
send any requests to such a host.

This variable gets persisted in `sphinxql_state` and must survive the daemon
restart.


### `log_debug_filter` variable

```sql
SET GLOBAL log_debug_filter = 'ReadLock'
```

Suppresses debug-level log entries that start with a given prefix. Default is
empty string, ie. do not suppress any entries.

This makes `searchd` less chatty at `debug` and higher `log_level` levels.

At the moment, this setting seem useful for testing and debugging only, and
normally you must not need to tweak it in production.


### `log_level` variable

```sql
SET GLOBAL log_level = {info | debug | debugv | debugvv}'
```

Sets the current logging level. Default (and minimum) level is `info`.

This variable is useful to temporarily enable debug logging in `searchd`, with
this or that verboseness level.

At the moment, this setting seem useful for testing and debugging only, and
normally you must not need to tweak it in production.


### `max_filters` variable

```sql
SET GLOBAL max_filters = 32
```

Sets the max number of filters (individual `WHERE` conditions) that
the SphinxAPI clients are allowed to send. Default is 256.


### `max_filter_values` variable

```sql
SET GLOBAL max_filter_values = 32
```

Sets the max number of values per a single filter (`WHERE` condition) that
the SphinxAPI clients are allowed to send. Default is 4096.


### `net_spin_msec` variable

```sql
SET GLOBAL net_spin_msec = 30
```

Sets the poller spinning period in the network thread. Default is 10 msec.

The usual thread CPU slice is basically in 5-10 msec range. (For the really
curious, a rather good starting point are the lines mentioning "targeted
preemption latency" and "minimal preemption granularity" in
`kernel/sched/fair.c` sources.)

Therefore, if a heavily loaded network thread calls `epoll_wait()` with even
a seemingly tiny 1 msec timeout, that thread could occasionally get preempted
and waste precious microseconds. According to an ancient internal benchmark that
we can neither easily reproduce nor disavow these days (or in other words: under
certain circumstances), that can result in quite a significant difference. More
specifically, internal notes report ~3000 rps without spinning (ie. with
`net_spin_msec = 0`) vs ~5000 rps with spinning.

Therefore, by default we choose to call `epoll_wait()` with zero timeouts for
the duration of `net_spin_msec`, so that our "actual" slice for network thread
is closer to those 10 msec, just in case we get a lot of incoming queries.


### `query_log_min_msec` variable

```sql
SET GLOBAL query_log_min_msec = 1000
```

Changes the minimum elapsed time threshold for the queries to get logged.
Default is 1000 msec, ie. log all queries over 1 sec. The allowed range is 0 to
3600000 (1 hour).


### `read_timeout` variable

```sql
SET GLOBAL read_timeout = 1
```

Sets the read timeout, aka the timeout to receive a specific request from
the SphinxAPI client. Counted in sec, default is 5.

See also [`client_timeout`](#client_timeout-variable) and
[`sphinxql_timeout`](#sphinxql_timeout-variable).


### `sphinxql_timeout` variable

```sql
SET GLOBAL sphinxql_timeout = 1
```

Sets the timeout between queries for SphinxQL client. Counted in sec, default is
900, or 15 minutes.

See also [`client_timeout`](#client_timeout-variable) and
[`read_timeout`](#read_timeout-variable).


### `sql_fail_filter` variable

```sql
SET GLOBAL sql_fail_filter = 'insert'
```

The "fail filter" is a simple early stage filter imposed on all the incoming
SphinxQL queries. Any incoming queries that match a given non-empty substring
will immediately fail with an error.

This is useful for emergency maintenance, just as [siege mode](#siege-mode).
The two mechanisms are independent of each other, ie. both fail filter and siege
mode can be turned on simultaneously.

As of v.3.2, the matching is simple, case-sensitive and bytewise. This is
likely to change in the future.

To remove the filter, set the value to an empty string.

```sql
SET GLOBAL sql_fail_filter = ''
```


### `sql_log_file` variable

```sql
SET GLOBAL sql_log_file = '/tmp/sphinxlog.sql'
```

SQL log lets you (temporarily) enable logging all the incoming SphinxQL queries,
in (almost) raw form. Compared to `query_log` directive, this logger:

  * logs *all* SphinxQL queries, not just searches;
  * does *not* log any SphinxAPI calls;
  * does *not* have any noticeable performance impact;
  * is stopped by default.

Queries are stored as received. A hardcoded `; /* EOQ */` separator and then
a newline are stored after every query, for parsing convenience. It's useful to
capture and later replay a stream of all client SphinxQL queries.

For performance reasons, SQL logging uses a rather big buffer (to the tune of
a few megabytes), so don't be alarmed when `tail` does not immediately display
something after your start this log.

To stop SQL logging (and close and flush the log file), set the value to
an empty string.

```sql
SET GLOBAL sql_log_file = ''
```

We do *not* recommend keeping SQL logging on for prolonged periods on loaded
systems, as it might use a lot of disk space.


Index config reference
-----------------------

This section should eventually contain the complete full-index configuration
directives reference, for the `index` sections of the `sphinx.conf` file.

If the directive you're looking for is not yet documented here, please refer to
the legacy [Sphinx v.2.x reference](sphinx2.html#confgroup-index). Beware that
the legacy reference may not be up to date.

Here's a complete list of index configuration directives.

  * [`agent`](sphinx2.html#conf-agent)
  * [`agent_blackhole`](sphinx2.html#conf-agent-blackhole)
  * [`agent_connect_timeout`](sphinx2.html#conf-agent-connect-timeout)
  * [`agent_persistent`](sphinx2.html#conf-agent-persistent)
  * [`agent_query_timeout`](sphinx2.html#conf-agent-query-timeout)
  * [`annot_eot`](#annot_eot-directive)
  * [`annot_field`](#annot_field-directive)
  * [`annot_scores`](#annot_scores-directive)
  * [`attr_bigint`](#attr_bigint-directive)
  * [`attr_bigint_set`](#attr_bigint_set-directive)
  * [`attr_blob`](#attr_blob-directive)
  * [`attr_bool`](#attr_bool-directive)
  * [`attr_float`](#attr_float-directive)
  * [`attr_float_array`](#attr_float_array-directive)
  * [`attr_int8_array`](#attr_int8_array-directive)
  * [`attr_int_array`](#attr_int_array-directive)
  * [`attr_json`](#attr_json-directive)
  * [`attr_string`](#attr_string-directive)
  * [`attr_uint`](#attr_uint-directive)
  * [`attr_uint_set`](#attr_uint_set-directive)
  * [`bigram_freq_words`](sphinx2.html#conf-bigram-freq-words)
  * [`bigram_index`](sphinx2.html#conf-bigram-index)
  * [`blend_chars`](sphinx2.html#conf-blend-chars)
  * [`blend_mixed_codes`](#blend_mixed_codes-directive)
  * [`blend_mode`](sphinx2.html#conf-blend-mode)
  * [`charset_table`](sphinx2.html#conf-charset-table)
  * [`create_index`](#create_index-directive)
  * [`docstore_block`](#docstore_block-directive)
  * [`docstore_comp`](#docstore_comp-directive)
  * [`docstore_type`](#docstore_type-directive)
  * [`embedded_limit`](sphinx2.html#conf-embedded-limit)
  * [`exceptions`](sphinx2.html#conf-exceptions)
  * [`expand_keywords`](sphinx2.html#conf-expand-keywords)
  * [`field`](#field-directive)
  * [`field_string`](#field_string-directive)
  * [`global_avg_field_lengths`](#global_avg_field_lengths-directive)
  * [`global_idf`](sphinx2.html#conf-global-idf)
  * [`ha_strategy`](sphinx2.html#conf-ha-strategy)
  * [`hl_fields`](#hl_fields-directive)
  * [`html_index_attrs`](sphinx2.html#conf-html-index-attrs)
  * [`html_remove_elements`](sphinx2.html#conf-html-remove-elements)
  * [`html_strip`](sphinx2.html#conf-html-strip)
  * [`ignore_chars`](sphinx2.html#conf-ignore-chars)
  * [`index_exact_words`](sphinx2.html#conf-index-exact-words)
  * [`index_field_lengths`](sphinx2.html#conf-index-field-lengths)
  * [`index_sp`](sphinx2.html#conf-index-sp)
  * [`index_tokclass_fields`](#index_tokclass_fields-directive)
  * [`index_tokhash_fields`](#index_tokhash_fields-directive)
  * [`index_trigram_fields`](#index_trigram_fields-directive)
  * [`index_words_clickstat_fields`](#index_words_clickstat_fields-directive)
  * [`index_zones`](sphinx2.html#conf-index-zones)
  * [`join_attrs`](#join_attrs-directive)
  * [`kbatch`](#kbatch-directive)
  * [`kbatch_source`](#kbatch_source-directive)
  * [`local`](sphinx2.html#conf-local)
  * [`mappings`](#mappings-directive)
  * [`min_infix_len`](sphinx2.html#conf-min-infix-len)
  * [`min_prefix_len`](sphinx2.html#conf-min-prefix-len)
  * [`min_stemming_len`](sphinx2.html#conf-min-stemming-len)
  * [`min_word_len`](sphinx2.html#conf-min-word-len)
  * [`mixed_codes_fields`](#mixed_codes_fields-directive)
  * [`mlock`](sphinx2.html#conf-mlock)
  * [`morphdict`](#morphdict-directive)
  * [`morphology`](sphinx2.html#conf-morphology)
  * [`ngram_chars`](sphinx2.html#conf-ngram-chars)
  * [`ngram_len`](sphinx2.html#conf-ngram-len)
  * [`ondisk_attrs`](sphinx2.html#conf-ondisk-attrs)
  * [`overshort_step`](sphinx2.html#conf-overshort-step)
  * [`path`](sphinx2.html#conf-path)
  * [`pq_max_rows`](#pq_max_rows-directive)
  * [`preopen`](sphinx2.html#conf-preopen)
  * [`pretrained_index`](#pretrained_index-directive)
  * [`query_words_clickstat`](#query_words_clickstat-directive)
  * [`regexp_filter`](sphinx2.html#conf-regexp-filter)
  * [`rt_mem_limit`](sphinx2.html#conf-rt-mem-limit)
  * [`source`](sphinx2.html#conf-source)
  * [`stopword_step`](sphinx2.html#conf-stopword-step)
  * [`stopwords`](sphinx2.html#conf-stopwords)
  * [`stopwords_unstemmed`](sphinx2.html#conf-stopwords-unstemmed)
  * [`stored_fields`](#stored_fields-directive)
  * [`stored_only_fields`](#stored_only_fields-directive)
  * [`tokclasses`](#tokclasses-directive)
  * [`type`](#index-type-directive)
  * [`updates_pool`](#updates_pool-directive)


### `annot_eot` directive

```bash
annot_eot = <separator_token>

# example
annot_eot = MyMagicSeparator
```

This directive configures a raw separator token for the annotations field, used
to separate the individual annotations with the field.

For more details, refer to the [annotations docs section](#using-annotations).


### `annot_field` directive

```bash
annot_field = <ft_field>

# example
annot_field = annots
```

This directive marks the specified field as the annotations field. The field
must be present in the index, ie. for RT indexes, it must be configured using
the `field` directive anyway.

For more details, refer to the [annotations docs section](#using-annotations).


### `annot_scores` directive

```bash
annot_scores = <json_attr>.<scores_array>

# example
annot_scores = j.annscores
```

This directive configures the JSON key to use for `annot_max_score` calculation.
Must be a top-level key and must point to a vector of floats (not doubles).

For more details, see the [annotations scores section](#annotations-scores).


### `attr_bigint` directive

```bash
attr_bigint = <attrname> [, <attrname> [, ...]]

# example
attr_bigint = price
```

This directive declares one (or more) `BIGINT` typed attribute in your index, or
in other words, a column that stores signed 64-bit integers.

For more details, see the ["Using index schemas"](#using-index-schemas) section.


### `attr_bigint_set` directive

```bash
attr_bigint_set = <attrname> [, <attrname> [, ...]]

# example
attr_bigint_set = tags, locations
```

This directive declares one (or more) `BIGINT_SET` typed attribute in your
index, or in other words, a column that stores sets of unique signed 64-bit
integers. Another name for these sets in Sphinx speak is MVA, meaning
multi-valued attributes.

For more details, see the ["Using index schemas"](#using-index-schemas) section.


### `attr_blob` directive

```bash
attr_blob = <attrname> [, <attrname> [, ...]]

# example
attr_blob = guid
attr_blob = md5hash, sha1hash
```

This directive declares one (or more) `BLOB` typed attribute in your index, or
in other words, a column that stores binary strings, with embedded zeroes.

For more details, see the ["Using index schemas"](#using-index-schemas) and the
["Using blob attributes"](#using-blob-attributes) sections.


### `attr_bool` directive

```bash
attr_bool = <attrname> [, <attrname> [, ...]]

# example
attr_bool = is_test, is_hidden
```

This directive declares one (or more) `BOOL` typed attribute in your index, or
in other words, a column that stores a boolean flag (0 or 1, false or true).

For more details, see the ["Using index schemas"](#using-index-schemas) section.


### `attr_float` directive

```bash
attr_float = <attrname> [, <attrname> [, ...]]

# example
attr_float = lat, lon
```

This directive declares one (or more) `FLOAT` typed attribute in your index, or
in other words, a column that stores a 32-bit floating-point value.

For more details, see the ["Using index schemas"](#using-index-schemas) section.


### `attr_float_array` directive

```bash
attr_float_array = <attrname> '[' <arraysize> ']' [, ...]

# example
attr_float_array = coeffs[3]
attr_float_array = vec1[64], vec2[128]

```

This directive declares one (or more) `FLOAT_ARRAY` typed attribute in your
index, or in other words, a column that stores an array of 32-bit floating-point
values. The dimensions (aka array sizes) should be specified along with the
names.

For more details, see the ["Using index schemas"](#using-index-schemas) and the
["Using array attributes"](#using-array-attributes) sections.


### `attr_int8_array` directive

```bash
attr_int8_array = <attrname> '[' <arraysize> ']' [, ...]

# example
attr_int8_array = smallguys[3]
attr_int8_array = vec1[64], vec2[128]

```

This directive declares one (or more) `INT8_ARRAY` typed attribute in your
index, or in other words, a column that stores an array of signed 8-bit integer
values. The dimensions (aka array sizes) should be specified along with the
names.

For more details, see the ["Using index schemas"](#using-index-schemas) and the
["Using array attributes"](#using-array-attributes) sections.


### `attr_int_array` directive

```bash
attr_int_array = <attrname> '[' <arraysize> ']' [, ...]

# example
attr_int_array = regularguys[3]
attr_int_array = vec1[64], vec2[128]

```

This directive declares one (or more) `INT_ARRAY` typed attribute in your index,
or in other words, a column that stores an array of signed 32-bit integer
values. The dimensions (aka array sizes) should be specified along with the
names.

For more details, see the ["Using index schemas"](#using-index-schemas) and the
["Using array attributes"](#using-array-attributes) sections.


### `attr_json` directive

```bash
attr_json = <attrname> [, <attrname> [, ...]]

# example
attr_json = params
```

This directive declares one (or more) `JSON` typed attribute in your index, or
in other words, a column that stores an arbitrary JSON object.

For more details, see the ["Using index schemas"](#using-index-schemas) and the
["Using JSON"](#using-json) sections.


### `attr_string` directive

```bash
attr_string = <attrname> [, <attrname> [, ...]]

# example
attr_json = params
```

This directive declares one (or more) `STRING` typed attribute in your index, or
in other words, a column that stores a text string.

For more details, see the ["Using index schemas"](#using-index-schemas) section.


### `attr_uint` directive

```bash
attr_uint = <attrname> [, <attrname> [, ...]]

# example
attr_uint = user_id
attr_uint = created_ts, verified_ts
```

This directive declares one (or more) `UINT` typed attribute in your index, or
in other words, a column that stores an unsigned 32-bit integer.

For more details, see the ["Using index schemas"](#using-index-schemas) section.


### `attr_uint_set` directive

```bash
attr_uint_set = <attrname> [, <attrname> [, ...]]

# example
attr_uint_set = tags, locations
```

This directive declares one (or more) `BIGINT_SET` typed attribute in your
index, or in other words, a column that stores sets of unique unsigned 32-bit
integers. Another name for these sets in Sphinx speak is MVA, meaning
multi-valued attributes.

For more details, see the ["Using index schemas"](#using-index-schemas) section.


### `blend_mixed_codes` directive

```bash
blend_mixed_codes = {0 | 1}

# example
blend_mixed_codes = 1
```

Whether to detect and index parts of the "mixed codes" (aka letter-digit mixes).
Defaults to 0, do not index.

For more info, see the ["Mixed codes"](#mixed-codes-with-letters-and-digits)
section.


### `create_index` directive

```bash
create_index = <index_name> on <attr_or_json_key>

# examples
create_index = idx_price on price
create_index = idx_name on params.author.name
```

This directive makes `indexer` create secondary indexes on attributes (or JSON
keys) when rebuilding the FT index. As of v.3.5, it's only supported for plain
indexes. You can use [`CREATE INDEX` statement](#create-index-syntax) for RT
indexes.

To create several attribute indexes, specify several respective `create_index`
directives, one for each index.

Index creation is batched, that is, `indexer` makes exactly one extra pass over
the attribute data, and populates *all* the indexes during that pass.

As of v.3.5, any index creation errors get reported as `indexer` warnings only,
not errors. The resulting FT index should still be generally usable, but it will
miss the attribute indexes.


### `docstore_block` directive

```bash
docstore_block = <size> # supports k and m suffixes

# example
docstore_block = 32k
```

Docstore target storage block size. Default is 16K, ie. 16384 bytes.

For more info, see the ["Using DocStore"](#using-docstore) section.


### `docstore_comp` directive

```bash
docstore_comp = {none | lz4 | lz4hc}
```

Docstore block compression method. Default is LZ4HC, ie. use slower but tigher
codec.

For more info, see the ["Using DocStore"](#using-docstore) section.


### `docstore_type` directive

```bash
docstore_type = {vblock | vblock_solid}
```

Docstore block compression type. Default is `vblock_solid`, ie. compress
the entire block rather than individual documents in it.

For more info, see the ["Using DocStore"](#using-docstore) section.


### `field` directive

```bash
field = <fieldname> [, <fieldname> [, ...]]

# example
field = title
field = content, texttags, abstract
```

This directive declares one (or more) full-text field in your index. At least
one field is required at all times.

Note that the original field contents are *not* stored by default. If required,
you can store them either in RAM as attributes, or on disk using DocStore. For
that, either use [`field_string`](#field_string-directive) *instead* of `field`
for the in-RAM attributes route, or [`stored_fields`](#stored_fields-directive)
in *addition* to `field` for the on-disk DocStore route, respectively.

For more details, see the ["Using index schemas"](#using-index-schemas) and the
["Using DocStore"](#using-docstore) sections.


### `field_string` directive

```bash
field_string = <fieldname> [, <fieldname> [, ...]]

# example
field_string = title, texttags
```

This directive double-declares one (or more) full-text field *and* the string
attribute (that automatically stores a copy of that field) in your index.

For more details, see the ["Using index schemas"](#using-index-schemas) section.


### `global_avg_field_lengths` directive

```bash
global_avg_field_lengths = <field1: avglen1> [, <field2: avglen2> ...]

# example
global_avg_field_lengths = title: 5.76, content: 138.24
```

A static list of field names and their respective average lengths (in tokens)
that overrides the dynamic lengths computed by `index_field_lengths` for BMxx
calculation purposes.

For more info, see the ["Ranking: field lengths"](#ranking-field-lengths)
section.


### `hl_fields` directive

```bash
hl_fields = <field1> [, <field2> ...]

# example
hl_fields = title, content
```

A list of fields that should store precomputed data at indexing time to speed up
snippets highlighting at searching time. Default is empty.

For more info, see the ["Using DocStore"](#using-docstore) section.


### `index_tokclass_fields` directive

```bash
index_tokclass_fields = <field1> [, <field2> ...]

# example
index_tokclass_fields = title
```

A list of fields to analyze for token classes and store the respective class
masks for, during the indexing time. Default is empty.

For more info, see the ["Ranking: token classes"](#ranking-token-classes)
section.


### `index_tokhash_fields` directive

```bash
index_tokhash_fields = <field1> [, <field2> ...]

# example
index_tokhash_fields = title
```

A list of fields to create internal token hashes for, during the indexing time.
Default is empty.

For more info, see the
["Ranking: tokhashes..."](#ranking-tokhashes-and-wordpair_ctr) section.


### `index_trigram_fields` directive

```bash
index_trigram_fields = <field1> [, <field2> ...]

# example
index_trigram_fields = title
```

A list of fields to create internal trigram filters for, during the indexing
time. Default is empty.

For more info, see the ["Ranking: trigrams"](#ranking-trigrams) section.


### `index_words_clickstat_fields` directive

```bash
index_words_clickstat_fields = <field1:tsv1> [, <field2:tsv2> ...]

# example
index_words_clickstat_fields = title:title_stats.tsv
```

A list of fields and their respective clickstats TSV tables, to compute static
`tokclicks` ranking signals during the indexing time. Default is empty.

For more info, see the ["Ranking: clickstats"](#ranking-clickstats) section.


### `join_attrs` directive

```bash
join_attrs = <index_attr[:joined_column]> [, ...]

# example
join_attrs = ts:ts, weight:score, price
```

A list of `index_attr:joined_column` pairs that binds target index attributes
to source joined columns, by their names.

For more info, see the ["Indexing: join sources"](#indexing-join-sources)
section.


### `kbatch` directive

```bash
kbatch = <index1> [, <index2> ...]

# example
kbatch = arc2019, arc2020, arc2021
```

A list of target K-batch indexes to delete the docids from. Default is empty.

For more info, see the ["Using K-batches"](#using-k-batches) section.


### `kbatch_source` directive

```bash
kbatch_source = {kl | id} [, {kl | id}]

# example
kbatch = kl, id
```

A list of docid sets to generate the K-batch from. Default is `kl`, ie. only
delete any docids if explicitly requested. The two known sets are:

  * `kl`, the explicitly provided docids (eg. from `sql_query_kbatch`)
  * `id`, all the newly-indexed docids

The example `kl, id` list merges the both sets. The resulting K-batch will
delete both all the explicitly requested docids *and* all of the newly indexed
docids.

For more info, see the ["Using K-batches"](#using-k-batches) section.


### `mappings` directive

```bash
mappings = <filename_or_mask> [<filename_or_mask> [...]]

# example
mappings = common.txt local.txt masked*.txt
mappings = part1.txt
mappings = part2.txt
mappings = part3.txt
```

A space-separated list of file names with the keyword mappings for this index.

Optional, default is empty. Multi-value, you can specify it multiple times, and
all the values from all the entries will be combined. Supports names masks aka
wildcards, such as the `masked*.txt` in the example.

For more info, see the ["Using mappings"](#using-mappings) section.


### `mixed_codes_fields` directive

```bash
mixed_codes_fields = <field1> [, <field2> ...]

# example
mixed_codes_fields = title, keywords
```

A list of fields that the mixed codes indexing is limited to. Optional, default
is empty, meaning that mixed codes should be detected and indexed in *all* the
fields when requested (ie. when `blend_mixed_codes = 1` is set).

For more info, see the ["Mixed codes"](#mixed-codes-with-letters-and-digits)
section.


### `morphdict` directive

```bash
morphdict = <filename_or_mask> [<filename_or_mask> [...]]

# example
morphdict = common.txt local.txt masked*.txt
morphdict = part1.txt
morphdict = part2.txt
morphdict = part3.txt
```

A space-separated list of file names with morphdicts, the (additional) custom
morphology dictionary entries for this index.

Optional, default is empty. Multi-value, you can specify it multiple times, and
all the values from all the entries will be combined. Supports names masks aka
wildcards, such as the `masked*.txt` entry in the example.

For more info, see the ["Using morphdict"](#using-morphdict) section.


### `pq_max_rows` directive

```bash
pq_max_rows = <COUNT>

# example
pq_max_rows = 1000
```

Max rows (stored queries) count, for PQ index type only. Optional, default is
1000000 (one million).

This limit only affects sanity checks, and prevents PQ indexes from unchecked
growth. It can be changed online.

For more info, see the [percolate queries](#searching-percolate-queries)
section.


### `pretrained_index` directive

```bash
pretrained_index = <filename>

# example
pretrained_index = pretrain01.bin
```

Pretrained vector index data file. When present, pretrained indexes can be used
to speed up building (larger) vector indexes. Default is empty.

For more info, see the [vector indexes](#searching-vector-indexes) section.


### `query_words_clickstat` directive

```bash
query_words_clickstat = <filename>

# example
query_words_clickstat = my_queries_clickstats.tsv
```

A single file name with clickstats for the query words. Its contents will be
used to compute the `words_clickstat` signal. Optional, default is empty.

For more info, see the ["Ranking: clickstats"](#ranking-clickstats) section.


### `stored_fields` directive

```bash
stored_fields = <field1> [, <field2> ...]

# example
stored_fields = abstract, content
```

A list of fields that must be both full-text indexed *and* stored in DocStore,
enabling future retrieval of the original field content in addition to `MATCH()`
searches. Optional, default is empty, meaning to store nothing in DocStore.

For more info, see the ["Using DocStore"](#using-docstore) section.


### `stored_only_fields` directive

```bash
stored_only_fields = <field1> [, <field2> ...]

# example
stored_only_fields = payload
```

A list of fields that must be stored in DocStore, and thus possible to retrieve
later, but *not* full-text indexed, and thus *not* searchable by the `MATCH()`
clause. Optional, default is empty.

For more info, see the ["Using DocStore"](#using-docstore) section.


### `tokclasses` directive

```bash
tokclasses = <class_id>:<filename> [, <class_id>:<filename> ...]

# example
tokclasses = 3:articles.txt, 15:colors.txt
```

A list of class ID number and token filename pairs that configures the token
classes indexing. Mandatory when the `index_tokclass_fields` list is set.
Allowed class IDs are from 0 to 29 inclusive.

For more info, see the ["Ranking: token classes"](#ranking-token-classes)
section.


### Index `type` directive

```bash
type = {plain | rt | distributed | template | pq}

# example
type = rt
```

Index type. Known values are `plain`, `rt`, `distributed`, `template`, and `pq`.
Optional, default is `plain`, meaning "plain" local index with limited writes.

Here's a brief summary of the supported index types.

  * **Plain index.** local physical index. Must be built offline with `indexer`;
    only supports limited online writes (namely `UPDATE` and `DELETE`); can be
    "converted" or "appended" to RT index using the `ATTACH` statement.

  * **RT index.** Local physical index. Fully supports online writes (`INSERT`
    and `REPLACE` and `UPDATE` and `DELETE`).

  * **Distributed index.** Virtual config-only index, essentially a list of
    other indexes, either local or remote. Supports reads properly (aggregates
    the results, does network retries, mirror selection, etc). But does not
    really support writes.

  * **Template index**. Virtual config-only index, essentially a set of indexing
    settings. Mostly intended to simplify config management by inheriting other
    indexes from templates. However, also supports a few special reads queries
    that only require settings and no index data, such as `CALL KEYWORDS`
    statement.

  * **PQ index**. Local physical index, for special "reverse" searches, aka
    percolate queries. Always has a hardcoded `bigint id, string query` schema.
    Supports basic reads and writes on its contents (aka the stored queries).
    Supports special `WHERE PQMATCH()` clause.

### `updates_pool` directive

```bash
updates_pool = <size>

# example
updates_pool = 1M
```

Vrow (variable-width row part) storage file growth step. Optional, supports size
suffixes, default is 64K. The allowed range is 64K to 128M.


Source config reference
------------------------

This section should eventually contain the complete data source configuration
directives reference, for the `source` sections of the `sphinx.conf` file.

If the directive you're looking for is not yet documented here, please refer to
the legacy [Sphinx v.2.x reference](sphinx2.html#confgroup-source). Beware that
the legacy reference may not be up to date.

Note how all these directives are only legal for certain subtypes of sources.
For instance, `sql_pass` only works with SQL sources (`mysql`, `pgsql`, etc),
and must not be used with CSV or XML ones.

Here's a complete list of data source configuration directives.

  * [`csvpipe_command`](#csvpipe_command-directive)
  * [`csvpipe_delimiter`](#csvpipe_delimiter-directive)
  * [`csvpipe_header`](#csvpipe_header-directive)
  * [`join_file`](#join_file-directive)
  * [`join_header`](#join_header-directive)
  * [`join_ids`](#join_ids-directive)
  * [`join_optional`](#join_optional-directive)
  * [`join_schema`](#join_schema-directive)
  * [`mssql_winauth`](sphinx2.html#conf-mssql-winauth)
  * [`mysql_connect_flags`](sphinx2.html#conf-mysql-connect-flags)
  * [`mysql_ssl_ca`](#mysql_ssl_ca-directive)
  * [`mysql_ssl_cert`](#mysql_ssl_cert-directive)
  * [`mysql_ssl_key`](#mysql_ssl_key-directive)
  * [`odbc_dsn`](sphinx2.html#conf-odbc-dsn)
  * [`sql_column_buffers`](sphinx2.html#conf-sql-column-buffers)
  * [`sql_db`](sphinx2.html#conf-sql-db)
  * [`sql_file_field`](sphinx2.html#conf-sql-file-field)
  * [`sql_host`](sphinx2.html#conf-sql-host)
  * [`sql_pass`](sphinx2.html#conf-sql-pass)
  * [`sql_port`](sphinx2.html#conf-sql-port)
  * [`sql_query`](sphinx2.html#conf-sql-query)
  * [`sql_query_kbatch`](#sql_query_kbatch-directive)
  * [`sql_query_post`](sphinx2.html#conf-sql-query-post)
  * [`sql_query_post_index`](sphinx2.html#conf-sql-query-post-index)
  * [`sql_query_pre`](sphinx2.html#conf-sql-query-pre)
  * [`sql_query_range`](sphinx2.html#conf-sql-query-range)
  * [`sql_query_set`](#sql_query_set-directive)
  * [`sql_query_set_range`](#sql_query_set_range-directive)
  * [`sql_range_step`](sphinx2.html#conf-sql-range-step)
  * [`sql_ranged_throttle`](sphinx2.html#conf-sql-ranged-throttle)
  * [`sql_sock`](sphinx2.html#conf-sql-sock)
  * [`sql_user`](sphinx2.html#conf-sql-user)
  * [`tsvpipe_command`](#tsvpipe_command-directive)
  * [`tsvpipe_header`](#tsvpipe_header-directive)
  * [`type`](#source-type-directive)
  * [`unpack_mysqlcompress`](sphinx2.html#conf-unpack-mysqlcompress)
  * [`unpack_mysqlcompress_maxsize`](sphinx2.html#conf-unpack-mysqlcompress-maxsize)
  * [`unpack_zlib`](sphinx2.html#conf-unpack-zlib)
  * [`xmlpipe_command`](sphinx2.html#conf-xmlpipe-command)
  * [`xmlpipe_fixup_utf8`](sphinx2.html#conf-xmlpipe-fixup-utf8)


### `csvpipe_command` directive

```bash
csvpipe_command = <shell_command>

# example
sql_query_kbatch = cat mydata.csv
```

A shell command to run and index the output as CSV.

See the ["Indexing: CSV and TSV files"](#indexing-csv-and-tsv-files) section for
more details.


### `csvpipe_delimiter` directive

```bash
csvpipe_delimiter = <delimiter_char>

# example
csvpipe_delimiter = ;
```

Column delimiter for indexing CSV sources. A single character, default is `,`
(the comma character).

See the ["Indexing: CSV and TSV files"](#indexing-csv-and-tsv-files) section for
more details.


### `csvpipe_header` directive

```bash
csvpipe_header = {0 | 1}

# example
csvpipe_header = 1
```

Whether to expect and handle a heading row with column names in the input CSV
when indexing CSV sources. Boolean flag (so 0 or 1), default is 0, no header.

See the ["Indexing: CSV and TSV files"](#indexing-csv-and-tsv-files) section for
more details.


### `join_file` directive

```bash
join_file = <FILENAME>
```

Data file to read the joined data from (in CSV format for `csvjoin` type, TSV
for `tsvjoin` type, or binary row format for `binjoin` type). Required for join
sources, forbidden in non-join sources.

For text formats, must store row data as defined in `join_schema` in the
respective CSV or TSV format.

For `binjoin` format, must store row data as defined in `join_schema` except
document IDs, in binary format.

See the ["Indexing: join sources"](#indexing-join-sources) section for more
details.


### `join_header` directive

```bash
join_header = {0 | 1}
```

Whether the first `join_file` line contains data, or a list of columns. Boolean
flag (so 0 or 1), default is 0, no header.

See the ["Indexing: join sources"](#indexing-join-sources) section for more
details.


### `join_ids` directive

```bash
join_ids = <FILENAME>
```

Binary file to read the joined document IDs from. For `binjoin` source type
only, forbidden in other source types.

Must store 8-byte document IDs, in binary format.

See the ["Indexing: join sources"](#indexing-join-sources) section for more
details.


### `join_optional` directive

```bash
join_optional = {1 | 0}
```

Whether the join source is optional, and `join_file` is allowed to be missing
and/or empty. Default is 0, ie. non-empty data files required.

See the ["Indexing: join sources"](#indexing-join-sources) section for more
details.


### `join_schema` directive

```bash
join_schema = bigint <COLNAME>, <type> <COLNAME> [, ...]

# example
join_schema = bigint id, float score, uint discount
```

The complete input `join_file` schema, with types and columns names. Required
for join sources, forbidden in non-join sources.

The supported types are `uint`, `bigint`, and `float`. The input column names
are case-insensitive. Arbitrary names are allowed (ie. proper identifiers are
not required), because they are only used for checks and binding.

See the ["Indexing: join sources"](#indexing-join-sources) section for more
details.


### `mysql_ssl_ca` directive

```bash
mysql_ssl_ca = <ca_file>

# example
mysql_ssl_ca = /etc/ssl/cacert.pem
```

SSL CA (Certificate Authority) file for MySQL indexing connections. If used,
must specify the same certificate used by the server. Optional, default is
empty. Applies to `mysql` source type only.

These directives let you set up secure SSL connection from `indexer` to MySQL.
For details on creating the certificates and setting up the MySQL server, refer
to MySQL documentation.


### `mysql_ssl_cert` directive

```bash
mysql_ssl_cert = <public_key>

# example
mysql_ssl_cert = /etc/ssl/client-cert.pem
```

Public client SSL key certificate file for MySQL indexing connections. Optional,
default is empty. Applies to `mysql` source type only.

These directives let you set up secure SSL connection from `indexer` to MySQL.
For details on creating the certificates and setting up the MySQL server, refer
to MySQL documentation.

### `mysql_ssl_key` directive

```bash
mysql_ssl_key = <private_key>

# example
mysql_ssl_key = /etc/ssl/client-key.pem
```

Private client SSL key certificate file for MySQL indexing connections.
Optional, default is empty. Applies to `mysql` source type only.

These directives let you set up secure SSL connection from `indexer` to MySQL.
For details on creating the certificates and setting up the MySQL server, refer
to MySQL documentation.


### `sql_query_kbatch` directive

```bash
sql_query_kbatch = <query>

# example
sql_query_kbatch = SELECT docid FROM deleted_queue
```

SQL query to fetch "deleted" document IDs to put into the one-off index K-batch
from the source database. Optional, defaults to empty.

On successful FT index load, all the fetched document IDs (as returned by this
query at the indexing time) will get deleted from *other* indexes listed in
the `kbatch` list.

For more info, see the ["Using K-batches"](#using-k-batches) section.


### `sql_query_set` directive

```bash
sql_query_set = <attr>: <query>

# example
sql_query_set = tags: SELECT docid, tagid FROM mytags
```

SQL query that fetches (all!) the docid-value pairs for a given integer set
attribute from its respective "external" storage. Optional, defaults to empty.

This is usually just an optimization. Most databases let you simply join with
the "external" table, group on document ID, and concatenate the tags. However,
moving the join to Sphinx indexer side might be (much) more efficient.


### `sql_query_set_range` directive

```bash
sql_query_set_range = <attr>: <query>

# example
sql_query_set_range = tags: SELECT MIN(docid), MAX(docid) FROM mytags
sql_query_set = tags: SELECT docid, tagid FROM mytags \
    WHERE docid BETWEEN $start AND $end
```

SQL query that fetches some min/max range, and enables `sql_query_set` to step
through range in chunks, rather than all once. Optional, defaults to empty.

This is usually just an optimization. Should be useful when the entire dataset
returned by `sql_query_set` is too large to handle for whatever reason (network
packet limits, super-feeble database, client library that can't manage to hold
its result set, whatever).


### `tsvpipe_command` directive

```bash
tsvpipe_command = <shell_command>

# example
tql_query_kbatch = cat mydata.tsv
```

A shell command to run and index the output as TSV.

See the ["Indexing: CSV and TSV files"](#indexing-csv-and-tsv-files) section for
more details.


### `tsvpipe_header` directive

```bash
tsvpipe_header = {0 | 1}

# example
tsvpipe_header = 1
```

Whether to expect and handle a heading row with column names in the input TSV
when indexing TSV sources. Boolean flag (so 0 or 1), default is 0, no header.

See the ["Indexing: CSV and TSV files"](#indexing-csv-and-tsv-files) section for
more details.


### Source `type` directive

```bash
type = {mysql | pgsql | odbc | mssql | csvpipe | tsvpipe | xmlpipe2}

# example
type = mysql
```

Data source type. Mandatory, does **not** have a default value, so you **must**
specify one. Known types are `mysql`, `pgsql`, `odbc`, `mssql`, `csvpipe`,
`tsvpipe`, and `xmlpipe2`.

For details, refer to the ["Indexing: data sources"](#indexing-data-sources)
section.


Common config reference
------------------------

This section should eventually contain the complete common configuration
directives reference, for the `common` section of the `sphinx.conf` file.

If the directive you're looking for is not yet documented here, please refer to
the legacy [Sphinx v.2.x reference](sphinx2.html#confgroup-common). Beware that
the legacy reference may not be up to date.

Here's a complete list of common configuration directives.

  * [`attrindex_thresh`](#attrindex_thresh-directive)
  * [`datadir`](#datadir-directive)
  * [`json_autoconv_keynames`](sphinx2.html#conf-json-autoconv-keynames)
  * [`json_autoconv_numbers`](sphinx2.html#conf-json-autoconv-numbers)
  * [`json_float`](#json_float-directive)
  * [`lemmatizer_base`](sphinx2.html#conf-lemmatizer-base)
  * [`on_json_attr_error`](sphinx2.html#conf-on-json-attr-error)
  * [`plugin_dir`](sphinx2.html#conf-plugin-dir)
  * [`plugin_libinit_arg`](#plugin_libinit_arg-directive)
  * [`vecindex_thresh`](#vecindex_thresh-directive)

### `attrindex_thresh` directive

```bash
attrindex_thresh = <num_rows>

# example
attrindex_thresh = 10000
```

Attribute index segment size threshold. Attribute indexes are only built for
segments with at least that many rows. Default is 1024.

For more info, see the ["Using attribute indexes"](#using-attribute-indexes)
section.


### `datadir` directive

```bash
datadir = <some_folder>

# example
datadir = /home/sphinx/sphinxdata
```

Base path for all the Sphinx data files. As of v.3.5, defaults to `./sphinxdata`
when there is no configuration file, and defaults to empty string otherwise.

For more info, see the ["Using datadir"](#using-datadir) section.


### `plugin_libinit_arg` directive

```bash
plugin_libinit_arg = <string>

# example
plugin_libinit_arg = hello world
```

An arbitrary custom text argument for `_libinit`, the UDF initialization call.
Optional, default is empty.

For more info, see the
["UDF library initialization"](#udf-library-initialization) section.


### `vecindex_thresh` directive

```bash
vecindex_thresh = <num_rows>

# example
vecindex_thresh = 10000
```

Vector index segment size threshold. Vector indexes are only built for
segments with at least that many rows. Default is 170000.

For more info, see the [vector indexes](#searching-vector-indexes) section.

### `json_float` directive

```bash
json_float = {float | double}
```

Default JSON floating-point values storage precision, used when there's no
explicit precision suffix. Optional, defaults to `float`.

`float` means 32-bit single-precision values and `double` means 64-bit
double-precision values as in IEEE 754 (or as in any sane C++ compiler).


`indexer` config reference
---------------------------

This section should eventually contain the complete `indexer` configuration
directives reference, for the `indexer` section of the `sphinx.conf` file.

If the directive you're looking for is not yet documented here, please refer to
the legacy [Sphinx v.2.x reference](sphinx2.html#confgroup-indexer). Beware that
the legacy reference may not be up to date.

Here's a complete list of `indexer` configuration directives.

  * [`lemmatizer_cache`](sphinx2.html#conf-lemmatizer-cache)
  * [`max_file_field_buffer`](sphinx2.html#conf-max-file-field-buffer)
  * [`max_iops`](sphinx2.html#conf-max-iops)
  * [`max_iosize`](sphinx2.html#conf-max-iosize)
  * [`max_xmlpipe2_field`](sphinx2.html#conf-max-xmlpipe2-field)
  * [`mem_limit`](sphinx2.html#conf-mem-limit)
  * [`on_file_field_error`](sphinx2.html#conf-on-file-field-error)
  * [`write_buffer`](sphinx2.html#conf-write-buffer)


`searchd` config reference
---------------------------

This section should eventually contain the complete `searchd` configuration
directives reference, for the `searchd` section of the `sphinx.conf` file.

If the directive you're looking for is not yet documented here, please refer to
the legacy [Sphinx v.2.x reference](sphinx2.html#confgroup-searchd). Beware that
the legacy reference may not be up to date.

Here's a complete list of `searchd` configuration directives.

  * [`agent_connect_timeout`](sphinx2.html#conf-agent-connect-timeout)
  * [`agent_query_timeout`](sphinx2.html#conf-agent-query-timeout)
  * [`agent_retry_count`](sphinx2.html#conf-agent-retry-count)
  * [`agent_retry_delay`](sphinx2.html#conf-agent-retry-delay)
  * [`auth_users`](#auth_users-directive)
  * [`binlog`](#binlog-directive)
  * [`binlog_flush_mode`](#binlog_flush_mode-directive)
  * [`binlog_max_log_size`](#binlog_max_log_size-directive)
  * [`binlog_path`](sphinx2.html#conf-binlog-path)
  * [`client_timeout`](sphinx2.html#conf-client-timeout)
  * [`collation_libc_locale`](sphinx2.html#conf-collation-libc-locale)
  * [`collation_server`](sphinx2.html#conf-collation-server)
  * [`dist_threads`](sphinx2.html#conf-dist-threads)
  * [`docstore_cache_size`](#docstore_cache_size-directive)
  * [`expansion_limit`](#expansion_limit-directive)
  * [`ha_period_karma`](sphinx2.html#conf-ha-period-karma)
  * [`ha_ping_interval`](sphinx2.html#conf-ha-ping-interval)
  * [`hostname_lookup`](sphinx2.html#conf-hostname-lookup)
  * [`listen`](sphinx2.html#conf-listen)
  * [`listen_backlog`](sphinx2.html#conf-listen-backlog)
  * [`log`](sphinx2.html#conf-log)
  * [`max_batch_queries`](sphinx2.html#conf-max-batch-queries)
  * [`max_children`](sphinx2.html#conf-max-children)
  * [`max_filter_values`](sphinx2.html#conf-max-filter-values)
  * [`max_filters`](sphinx2.html#conf-max-filters)
  * [`max_packet_size`](sphinx2.html#conf-max-packet-size)
  * [`meta_slug`](#meta_slug-directive)
  * [`mysql_version_string`](sphinx2.html#conf-mysql-version-string)
  * [`net_spin_msec`](#net_spin_msec-directive)
  * [`net_workers`](sphinx2.html#conf-net-workers)
  * [`ondisk_attrs_default`](sphinx2.html#conf-ondisk-attrs-default)
  * [`persistent_connections_limit`](sphinx2.html#conf-persistent-connections-limit)
  * [`pid_file`](sphinx2.html#conf-pid-file)
  * [`predicted_time_costs`](sphinx2.html#conf-predicted-time-costs)
  * [`preopen_indexes`](sphinx2.html#conf-preopen-indexes)
  * [`qcache_max_bytes`](sphinx2.html#conf-qcache-max-bytes)
  * [`qcache_thresh_msec`](sphinx2.html#conf-qcache-thresh-msec)
  * [`qcache_ttl_sec`](sphinx2.html#conf-qcache-ttl-sec)
  * [`query_log`](sphinx2.html#conf-query-log)
  * [`query_log_min_msec`](sphinx2.html#conf-query-log-min-msec)
  * [`queue_max_length`](sphinx2.html#conf-queue-max-length)
  * [`read_buffer`](sphinx2.html#conf-read-buffer)
  * [`read_timeout`](sphinx2.html#conf-read-timeout)
  * [`read_unhinted`](sphinx2.html#conf-read-unhinted)
  * [`rt_flush_period`](sphinx2.html#conf-rt-flush-period)
  * [`rt_merge_iops`](sphinx2.html#conf-rt-merge-iops)
  * [`rt_merge_maxiosize`](sphinx2.html#conf-rt-merge-maxiosize)
  * [`seamless_rotate`](sphinx2.html#conf-seamless-rotate)
  * [`shutdown_timeout`](sphinx2.html#conf-shutdown-timeout)
  * [`snippets_file_prefix`](sphinx2.html#conf-snippets-file-prefix)
  * [`sphinxql_state`](sphinx2.html#conf-sphinxql-state)
  * [`sphinxql_timeout`](sphinx2.html#conf-sphinxql-timeout)
  * [`thread_stack`](sphinx2.html#conf-thread-stack)
  * [`unlink_old`](sphinx2.html#conf-unlink-old)
  * [`watchdog`](sphinx2.html#conf-watchdog)
  * [`wordpairs_ctr_file`](#wordpairs_ctr_file-directive)
  * [`workers`](sphinx2.html#conf-workers)


### `auth_users` directive

```bash
auth_users = <users_file.csv>
```

Users auth file. Default is empty, meaning that no user auth is required. When
specified, forces the connecting clients to provide a valid user/password pair.

For more info, see the ["Operations: user auth"](#operations-user-auth) section.


### `binlog` directive

```bash
binlog = {0 | 1}
```

Binlog toggle for the datadir mode. Default is 1, meaning that binlogs (aka WAL,
write-ahead log) are enabled, and FT index writes are safe.

This directive only affects the datadir mode, and is ignored in the legacy
non-datadir mode.


### `binlog_flush_mode` directive

```bash
binlog_flush_mode = {0 | 1 | 2}

# example
binlog_flush = 1 # ultimate safety, low speed
```

Binlog per-transaction flush and sync mode. Optional, defaults to 2, meaning to
call `fflush()` every transaction, and `fsync()` every second.

This directive controls `searchd` flushing the binlog to OS, and syncing it to
disk. Three modes are supported:

- mode 0, `fflush()` and `fsync()` every second.
- mode 1, `fflush()` and `fsync()` every transaction.
- mode 2, `fflush()` every transaction, `fsync()` every second.

Mode 0 yields the best performance, but comparatively unsafe, as up to 1 second
of recently committed writes can get lost either on `searchd` crash, or server
(hardware or OS) crash.

Mode 1 yields the worst performance, but provides the strongest guarantees.
Every single committed write **must** survive both `searchd` crashes *and*
server crashes in this mode.

Mode 2 is a reasonable hybrid, as it yields decent performance, and guarantees
that every single committed write **must** survive the `searchd` crash (but not
the server crash). You could still lose up to 1 second worth of confirmed writes
on a (recoverable) server crash, but those are rare, so most frequently this is
a perfectly acceptable tradeoff.


### `binlog_max_log_size` directive

```bash
binlog_max_log_size = <size>

# example
binlog_flush = 1G
```

Maximum binlog (WAL) file size. Optional, default is 128 MB.

A new binlog file will be forcibly created once the current file reaches this
size limit. This makes the logs files set a bit more manageable.

Setting the max size to 0 removes the size limit. The log file will keep growing
until the next FT index flush, or restart, etc.


### `docstore_cache_size` directive

```bash
docstore_block = <size> # supports k and m suffixes

# example
docstore_cache_size = 256M
```

Docstore global cache size limit. Default is 10M, ie. 10485760 bytes.

This directive controls how much RAM can `searchd` spend for caching individual
docstore blocks (for all the indexes).

For more info, see the ["Using DocStore"](#using-docstore) section.


### `expansion_limit` directive

```bash
expansion_limit = <count>

# example
expansion_limit = 1000
```

The maximum number of keywords to expand a single wildcard into. Optional,
default is 0 (no limit).

Wildcard searches may potentially expand wildcards into thousands and even
millions of individual keywords. Think of matching `a*` against the entire
Oxford dictionary. While good for recall, that's not great for performance.

**This directive imposes a server-wide expansion limit**, restricting wildcard
searches and reducing their performance impact. However, this is *not* a global
hard limit! Meaning that **individual queries can override it** on the fly,
using the `OPTION expansion_limit` clause.

`expansion_limit = N` means that every single wildcard may expand to at most N
keywords. Top-N matching keywords by frequency are guaranteed to be selected for
every wildcard. That ensures the best possible recall.

Note that this always is a tradeoff. Setting a smaller `expansion_limit` helps
performance, but hurts recall. Search results will have to omit documents that
match on more rare expansions. The smaller the limit, the more results *may* get
dropped.

But overshooting `expansion_limit` isn't great either. Super-common wildcards
can hurt performance brutally. In absence of any limits, deceptively innocent
`WHERE MATCH('a*')` search might easily explode into literally 100,000s of
individual keywords, and slow down to a crawl.

Unfortunately, the specific performance-vs-recall sweet spot varies enormously
across datasets and queries. A good tradeoff value can get as low as just 20, or
as high as 50000. To find an `expansion_limit` value that works best, you have
to analyze your specific queries, actual expansions, latency targets, etc.


### `meta_slug` directive

```bash
meta_slug = <slug_string>

# examples
meta_slug = shard1
meta_slug = $hostname
```

Server-wide query metainfo slug (as returned in `SHOW META`). Default is empty.
Gets processed once on daemon startup, and `$hostname` macro gets expanded to
the current host name, obtained with a `gethostname()` call.

When non-empty, adds a slug to all the metas, so that `SHOW META` query starts
returning an additional key (naturally called `slug`) with the server-wide slug
value. Furthermore, in distributed indexes metas are aggregated, meaning that
in that case `SHOW META` is going to return *all* the slugs from all the agents.

This helps identify the specific hosts (replicas really) that produced
a specific result set in a scenario when there are several agent mirrors. Quite
useful for tracing and debugging.


### `net_spin_msec` directive

```bash
net_spin_msec = <spin_wait_timeout>

# example
net_spin_msec = 0
```

Allows the network thread to spin for this many milliseconds, ie. call `epoll()`
(or its equivalent) with zero timeout. Default is 10 msec.

After spinning for `net_spin_msec` with no incoming events, the network thread
switches to calling `epoll()` with 1 msec timeout. Setting this to 0 fully
disables spinning, and `epoll()` is *always* called with 1 msec timeout.

On some systems, spinning for the default 10 msec value seems to improve query
throughput under high query load (as in 1000 rps and more). On other systems
and/or with different load patterns, the impact could be negligible, you may
waste a bit of CPU for nothing, and zero spinning would be better. YMMV.


### `wordpairs_ctr_file` directive

```bash
wordpairs_ctr_file = <path>

# example
wordpairs_ctr_file = query2doc.tsv
```

Specifies a data file to use for `wordpair_ctr` ranking signal and
`WORDPAIRCTR()` function calculations.

For more info, see the
["Ranking: tokhashes..."](#ranking-tokhashes-and-wordpair_ctr) section.


`indexer` CLI reference
------------------------

`indexer` is most frequently invoked with the `build` subcommand (that fully
rebuilds an FT index), but there's more to it than that!

| Command    | Action                                     |
|------------|--------------------------------------------|
| build      | reindex one or more FT indexes             |
| buildstops | build stopwords from FT index data sources |
| help       | show help for a given command              |
| merge      | merge two FT indexes                       |
| pretrain   | pretrain vector index clusters             |
| version    | show version and build options             |

Let's quickly overview those.

**`build` subcommand creates a plain FT index from source data.** You use this
one to fully rebuild the entire index. Depending on your setup, rebuilds might
be either as frequent as every minute (to rebuild and ship tiny delta indexes),
or as rare as "during disaster recovery only" (including drills).

**`buildstops` subcommand extracts stopwords without creating any index.**
That's definitely not an everyday activity, but a somewhat useful tool when
initially configuring your indexes.

**`merge` subcommand physically merges two existing plain FT indexes.** Also,
it optimizes the target index as it goes. Again depending on your specific index
setup, this might either be a part of everyday workflow (think of merging new
per-day data into archives during overnight maintenance), or never ever needed.

**`pretrain` subcommand creates pretrained clusters for vector indexes.** Very
definitely not an everyday activity, too, but **essential** for vector indexing
performance when rebuilding larger indexes. Because without clusters pretrained
on data that you hand-picked upfront, Sphinx for now defaults to reclustering
the **entire** input dataset. And for 100+ million row datasets that's not going
to be fast!

All subcommands come with their own options. You can use `help` to quickly
navigate those. Here's one example output.

```
$ indexer help buildstops
Usage: indexer buildstops --out <top.txt> [OPTIONS] <index1> [<index2> ...]

Builds a list of top-N most frequent keywords from the index data sources.
That provides a useful baseline for stopwords.

Options are:
   --ask-password    prompt for password, override `sql_pass` in SQL sources
   --buildfreqs      include words frequencies in <output.txt>
   --noprogress      do not display progress (automatic when not on a TTY)
   --out <top.txt>   save output in <top.txt> (required)
   --password <secret>
                     override `sql_pass` in SQL sources with <secret>
   --top <N>         pick top <N> keywords (default is 100)
```

TODO: document all individual indexer subcommands and their options!


`searchd` CLI reference
------------------------

The primary `searchd` operation mode is to run as a daemon, and serve queries.
Unless you specify an explicit subcommand, it does that. However, it supports
a few more subcommands.

| Command       | Action                                               |
|---------------|------------------------------------------------------|
| decode        | decode SphinxAPI query dump and print it as SphinxQL |
| --delete      | delete Windows service                               |
| -h, --help    | print a help screen                                  |
| --install     | install Windows service                              |
| --status      | print status variables from the running instance     |
| --stop        | stop the running instance                            |
| --stopwait    | stop the running instance, and wait for it to exit   |
| -v, --version | print a version and build info screen                |

### `searchd decode` command

```bash
searchd decode <dump>
searchd decode -
```

Decodes SphinxAPI query dump (as seen in the dreaded crash reports in the log),
formats that query as SphinxQL, and exits.

You can either pass the entire base64-encoded dump as an argument string, or
have `searchd` read it from stdin using the `searchd decode -` syntax.

Newlines are ignored. Whitespace is not (fails at the base64 decoder).

Examples!

```
$ searchd decode "ABCDEFGH" -q
FATAL: decode failed: unsupported API command code 16, expected COMMAND_SEARCH

$ cat dump
AAABJAAAAQAAAAAgAAAAAQAAAAAAAAABAAAAAAAAABQAAAAAAAAAAAAAAAQA
AAANd2VpZ2h0KCkgZGVzYwAAAAAAAAAAAAAAA3J0MQAAAAEAAAAAAAAAAAAA
AAAAAAAAAAAAAAAAAAQAAAAAAyAAAAAAAA1AZ3JvdXBieSBkZXNjAAAAAAAA
AAAAAAH0AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAEqAAAAAAAA
AAAAAAAAAAAAAAAAAAAAAAANd2VpZ2h0KCkgZGVzYwAAAAEAAAAAAAAAAAAA
AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA//////////8A

$ cat dump | searchd decode - -q
SELECT * FROM rt1;
```


Changes in 3.x
---------------

### Version 3.7.1, 28 mar 2024

Major new features:

* added [percolate queries and indexes](#searching-percolate-queries) support
* added [`json_float`](#json_float-directive) directive to set the default JSON
  float format, and switched to 32-bit float by default
* added [new `indexer` CLI](#indexer-cli-reference) with proper subcommands
* added [indexer-side joins](#indexing-join-sources) support, `indexer` can now
  join numeric and array columns from CSV, TSV, and binary files

New features:

* added `KEEP` clause support to [`REPLACE`](#replace-syntax)
* added [`L1DIST()` function](#l1dist-function) that computes an L1 distance
  (aka Manhattan or grid distance) between two vectors
* added [`MINGEODISTEX()` function](#mingeodistex-function) variant that also
  returns the nearest point's index
* added base64-encoded `INT8_ARRAY` support to `UPDATE` statements too, see
  ["Using array attributes"](#using-array-attributes)
* added [`double[]` JSON syntax extension](#using-json) for 64-bit float arrays
* added [`PESSIMIZE_RANK()`](#pessimize_rank-table-function) table function
* added `LIMIT` clause support to [table functions](#using-table-functions)
* added optional `IF EXISTS` clause to [`DROP TABLE`](#drop-table-syntax)
* added per-query [`OPTION expansion_limit`](#select-options)
* added missing `G` (giga) suffix support in some query/index options

Deprecations and removals:

* removed `json_packed_keys` directive (deprecated since early 2023)
* banned `FACET` in subselects, unpredictable (and kinda meaningless) behavior
* banned `FACTORS()` use in `WHERE` clause (for now) to hotfix crashes
* deprecated legacy indexer CLI, [use commands!](#indexer-cli-reference)

Changes and improvements:

* improved JSON to default to 32-bit floats, this saves both storage and CPU
* improved `indexer build --dump-tsv-rows` to emit new `attr_xxx` directives
* improved `create_index` syntax and distributed agents syntax checks in
  `indextool checkconfig`
* improved `expansion_limit` to be properly index-wide, not per-segment (ugh)
* improved vector indexes to better detect "too narrow" `WHERE` conditions, and
  auto-disengage (otherwise some queries with those would occasionally return
  partial results)
* optimized vector indexes to auto-engage earlier (specifically, fine-tuned
  the respective costs in query optimizer)

Fixes:

* fixed a crash on creating an attribute index on an empty disk segment
* fixed a crash on flushing RT segments with zero alive rows
* fixed a (rare) crash on saving `MULTIGEO` and maybe other attrindexes
* fixed a number of (rare) crashes with prefix searches
* fixed a bug in multi-row updates WAL replay
* fixed a number of int32 overflow related bugs vs "big enough" indexes
* fixed that some types of RAM segment loading issues were not reported
* fixed that wildcard searches (eg. `*foo*`) failed in certain corner cases
* fixed keyword expansions in `SHOW PLAN` (made them properly expanded)
* fixed a performance issue with (wrongly) duplicated AST keywords in RT under
  certain conditions
* fixed a few highlighting issues in `SNIPPETS()`
* fixed query parsing errors on certain valid queries (with some very specific
  `mappings` combinations, and phrase operators)
* fixed `agent_retry_count` behavior where 1 actually mean 0 retries (oops)
* fixed `searchd.pid` file handling that would sometimes break `searchd --stop`
* fixed spurious post-ATTACH warnings
* fixed spurious `searchd.log` errors caused by `CREATE INDEX` WAL entries
* fixed and improved a number of other warning and error messages


### Version 3.6.1, 04 oct 2023

Major new features:

* added [multigeo support](#multigeo-support), ie. multiple 2D geopoints per
   document, `MINGEODIST()` and `CONTAINSANY()` query functions, and special
   `MULTIGEO()` attribute indexes that can speed up `MINGEODIST()` queries
* added unified [`attr_xxx` syntax](#using-index-schemas) to declare field and
  attributes at index level (and [`sql_query_set`](#sql_query_set-directive) and
  [`sql_query_set_range`](#sql_query_set_range-directive) source directives that
  must now be used for "external" MVAs)
* added initial [user authentication](#operations-user-auth) support
* added [ANN vector index support](#searching-vector-indexes) support (for now,
  in private builds only)

New features:

* added (semi-debugging) `indextool dumpjsonkeys` command
* added support for most of the index settings and for array attributes to
  [`CREATE TABLE`](#create-table-syntax)
* added distributed indexes support to
  [`CALL KEYWORDS`](sphinx2.html#sphinxql-call-keywords)
* added [runtime index sampling](#index-sampling) with `sample_div` and
  `sample_min` query options
* added [base64 format support](#using-array-attributes) for incoming
  `INT8_ARRAY` values
* added [API crash dump query decoder](#searchd-decode-command) to `searchd`
* added [weighted round-robin strategy](#ha_weight-variable) for HA agents
* added most document-level signals to [`FACTORS().xxx`](#factors-function)
  subscript syntax variant
* added [`meta_slug`](#meta_slug-directive) and `SHOW META` slug output
* added [`global_avg_field_lengths`](#global_avg_field_lengths-directive)
  directive to set static average field lengths for BM25, see
  ["Ranking: field lengths"](#ranking-field-lengths) for details

Deprecations and removals:

* removed legacy per-segment `SHOW INDEX SETTINGS` syntax
* removed legacy (and misleading!) `bm25` and `proximity_bm25` ranker names (use
  proper `bm15` and `proximity_bm15` names instead)

Changes and improvements:

* improved `indextool` command syntax and built-in help
* improved `SHOW THREADS` output (now prioritizing comments when truncating
  queries to fit in the width), and removed the internal width limits
* improved [distributed error handling](#searching-distributed-query-errors) and
  made it more strict by default (ie. `OPTION lax_agent_errors=1`); individual
  component (index or agent) errors now fail the entire query
* improved query optimizer vs unindexed numeric columns, we now maintain
  histograms even on unindexed columns for the optimizer to use
* improved `INSERT` type compatibility checks
* improved slow `INSERT` and `REPLACE` logging, added CPU time stats
* improved batched select handling, `SHOW META` is now only allowed at the very
  end of the batch (it only ever worked at that location anyway)
* improved slow log output, multi-line queries should now be folded
* updated `indextool` to support datadir, and fixed a number of issues there too
* optimized `GROUP BY` finalization pass (helps heavy final UDFs)
* optimized RT disk segments matches finalization pass (helps heavy final UDFs)
* optimized RT segment traversal to RAM-then-disk (helps early-out searches)

Fixes:

* fixed that `SHOW CREATE TABLE` misreported simple fields as `field_string`
* fixed a crash caused by mixing `GROUP_COUNT` vs facets (or multiqueries)
* fixed that JSON column updates with an invalid value (such as malformed JSON)
  incorrectly wiped the value instead of failing the update
* fixed that `json.key BETWEEN` clause failed to parse negative numbers
* fixed "out of sync" SphinxQL client errors vs maxed out `thread_pool` server
* fixed a rare crash in query parser caused by certain specific multi-mappings
  within a phrase operator
* fixed that binlog entries could rarely get reordered and prevent replay
* fixed spurious "missing from VFS" errors (caused by empty filename lookups)
* fixed that distributed `SELECT` queries with a composite `GROUP BY` key
  ignored key parts that originated from JSON
* fixed a crash caused by `global_idf` vs empty file paths
* fixed a number of data races and halted jobs in distributed `SELECT` queries
* fixed retry count amplification when using `agent_retry_count` and mirrors
* fixed `id` checks in `INSERT`, and enabled zero docids via `INSERT`
* fixed a rare division-by-zero crash (in one of the `thread_wait` metrics)
* fixed `indexer --rotate` vs datadir mode
* fixed spurious exceptions warning caused by missing metadata on `ATTACH`
* fixed incorrectly inverted checks in JSON fields comparisons operators
* fixed a possible infinite loop in query parser under certain rare occasions
* fixed subselect sort limits handling differences between local vs distributed
  nested `SELECT` queries
* fixed a possible crash on (illegal) empty "agent =" config directive
* fixed incorrect error messages for some of the deprecated config keys
* fixed a crash in (illegal) `FLUSH RAMCHUNK` or `FLUSH RTINDEX` on plain index
* fixed a possible deadlock on kbatch vs greedy index reload
* fixed a rare crash on extremely long phrases in `MATCH()`


### Version 3.5.1, 02 feb 2023

Major new features:

* added [UDF call batching](#udf-call-batching) support for no-text queries
  without the `MATCH()` clause
* added array attributes support to `UPDATE` statement
* added [datadir support](#using-datadir)
* added [annotations support](#using-annotations)
* added [token hashes indexing](#ranking-tokhashes-and-wordpair_ctr), also see
  `index_tokhash_fields` directive
* added [`wordpair_ctr` ranking signal](#ranking-tokhashes-and-wordpair_ctr)
  based on token hashes, and the respective `WORDPAIRCTR()` function
* added [clickstat ranking signals](#ranking-clickstats)
* added [token class ranking signals](#ranking-token-classes)
* added [`GROUP_COUNT()` function](#group_count-function) that quickly computes
  per-group counts without doing full actual `GROUP BY`
* added [`BLOB` attribute type](#using-blob-attributes)
* added [per-index binlogs](#operations-binlogs), and made (multi-index) binlog
  replay multi-threaded, using up to 8 threads
* added [simpler sorting and grouping RAM budgets](#searching-memory-budgets),
  added `sort_mem` option in `SELECT` (and rewritten `ORDER BY`, `FACET` and
  `GROUP BY` internally to support all that)
* added attribute indexes support to "plain" full-text indexes: added
  [`create_index` directive](#create_index-directive), and enabled `EXPLAIN` and
  `CREATE INDEX` statements to support plain indexes
* added [`FACTORS('alternate ranking terms')` support](#xfactors) ie. an option
  to compute `FACTORS()` over an arbitrary text query, even for non-text queries
* added [`UPDATE INPLACE`](#update-syntax) support for in-place JSON updates;
  added [`BULK UPDATE INPLACE`](#bulk-update-syntax) syntax too

New features:

* added `INSERT` and `REPLACE` statement logging to (slow)
  [query log](#operations-query-logs)
* added `LIKE` clause to [`SHOW PROFILE`](#show-profile-syntax) statement
* added [optional xxx_libinit UDF call](#udf-library-initialization), and
  `plugin_libinit_arg` directive
* added `--ask-password` and `--password` switches to `indexer`
* added `FACTORS().xxx.yyy` syntax support
* added [initial `mysqldump` support](#operations-dumping-data), including
  initial `SHOW CREATE TABLE` etc statements support
* added distributed profiling to `SHOW PROFILE`
* added `cpu_stats` support to `SET`
* added initial [DataGrip IDE](https://www.jetbrains.com/datagrip/) support
* added [`INTERSECT_LEN()`](#intersect_len-function) function
* added `BIGINT_SET()` type helper, currently used in `INTERSECT_LEN()` only
* added [`SELECT @uservar`](#select-uservar-syntax) support
* added both global and per-index warning counters
* added top-level array type support to JSON parser

Deprecations and removals:

* deprecated `query_log_format` config directive (remove it from the configs;
  legacy `plain` format is now scheduled for removal)
* deprecated `json_packed_keys` config directive (remove it from the configs;
  JSON key packing is now scheduled for removal)
* deprecated `PACKEDFACTORS()` function alias (use `FACTORS()` now)
* deprecated `max_matches` option (use `LIMIT` now)
* removed buggy mixing of full JSON updates vs partial, inplace JSON updates
  from `UPDATE` explicitly (that never worked anyway)
* removed `RECONFIGURE` option from `ALTER RTINDEX ftindex`
* removed legacy `wordforms` compatibility code (use `mappings` now)
* removed legacy `RANKFACTORS()` function and `ranker=export()` option (use
  `FACTORS()` and `ranker=expr` now)
* removed buggy `BULK UPDATE` over JSON fields with string values

Changes and improvements:

* changed `query_log_format` (now deprecated) and `query_log_min_msec` defaults,
  to new SphinxQL format and saner 1000 msec respectively (was plain and 0, duh)
* changed how `indexer` handles explicitly specified attributes missing from
  `sql_query` result, this is now a hard error (was a warning)
* changed `bm15` signal type from int to float
* changed (increased) minimum possible thread stack size to 128K
* changed JSON float and double formatting to Sphinx implementation
* changed builds to always bundle jemalloc
* optimized DocStore vs `OPTIMIZE`, now avoiding redundant recompression
* optimized a special "frequent zone vs rare matches" case
* optimized indexing a little (getting 2-5% faster on our benchmarks)
* added missing `rank_fields` support to distributed indexes
* added a few missing query options to SQL parser and logger
* added `thread_wait_xxx` metrics to `SHOW STATUS`
* added a few more server variables support to dynamic `SET GLOBAL`
* added `searchd` autorestart on contiguous `accept()` failure, to autoheal on
  occasional file descriptor leaks
* added "compiled features" report to `-v` switch in `indexer` and `searchd`
* added float column vs integer set support to `WHERE`, ie. `WHERE f IN (3,15)`
* added checks for illegal less-or-equal-than-zero `LIMIT` values
* added simple conflicts checks for `charset_table`
* added `LIKE` clause to `SHOW INTERNAL STATUS`
* added missing float/int8 support in arithmetic ops over JSON
* improved `updates_pool` checks, enforcing 64K..128M range now
* improved `stopwords` directive syntax, multiple directives, per-line entries,
  and wildcards are now supported
* improved `SHOW THREADS` and enabled arbitrary high `OPTION columns = <width>`
* improved log deduping, dupes now only flush every 1 second (previously they
  also flushed every 100 dupes, which is not enough under extreme flooding)
* improved `FACTORS()` and `BM25F()`, made them autoswitch to expression ranker
* improved the "default" aggregate values precision; `GROUP BY` queries that fit
  into the (much higher) default memory budget must now be completely precise
* improved `[BULK] UPDATE` values and conversions checks, more strict now
  (eg. removed error-prone int-vs-float autoconversions in `BULK` version)
* improved `[BULK] UPDATE` error reporting, more verbose now

Fixes:

* fixed that `BM25F()` parsed integer arguments incorrectly (expected floats!)
* fixed that `NULL` strings and blobs were changed to empty in distributed indexes
* fixed several rare `ORDER BY` issues (namely: issues when attempting to sort
  on fancier JSON key types such as string arrays; rare false lookup failures on
  actually present but "unluckily" named JSON keys; mistakenly reverting to
  default order sometimes when doing a distributed nested select)
* fixed a possible division by zero in siege mode
* fixed `SHOW META` vs facet queries
* fixed `ORDER BY RAND()` vs distributed indexes
* fixed `FACET json.key` vs distributed indexes
* fixed that individual rows could go missing in certain full-text vs attribute
  index search edge cases (off-by-one rowset hints skipping an extra row)
* fixed distributed indexes (and SphinxAPI) vs semi-numeric JSON keys, such as
  `jsoncol.2022_11`
* fixed `rt_flush_period` default value and allowed range to be saner (we now
  default to flushing every 600 sec, and allow periods from 10 sec to 1 hour)
* fixed a race in docstore that sometimes resulted in missing documents
* fixed that `phrase_decayXX` signals sometimes yielded NaN or wrong values
* fixed rare `searchd` deadlocks on rename failures during rotate/RELOAD, and on
  a few other kinds of fatal errors
* fixed that `WHERE json.field BETWEEN const1 AND const2` clause was wrongly
  forcing numeric conversion
* fixed short `CREATE INDEX ON` statement form vs JSON columns
* fixed broken periodic binlog flushes (they could stop "forever")
* fixed occasional deadlocks in `OPTIMIZE`
* fixed two `GEODIST()` issues (bad option syntax was not reported; out of range
  latitudes sometimes produced wrong distances)
* fixed a few memory leaks (all estimated as small)
* fixed a performance issue that huge outliers disengaged attribute indexes
  (rewritten the indexed values histograms calculations for that)
* fixed a few messages here and there (errors on `--rotate` in `indexer`,
  `SHOW OPTIMIZE STATUS` wording, etc)
* fixed that `RELOAD PLUGINS` sometimes caused still-dangling UDF pointers
* fixed that file-related issues on index rotation were misreported
* fixed that `ATTACH` erroneously changed the target RT index schema for empty
  targets even when `TRUNCATE` option was not specified
* fixed occasional range querying issues vs Postgres sources in `indexer`
* fixed that `exact_hit` and `exact_field_hit` signals were sometimes a bit off
  (in a fringe case with phrase queries vs mixed codes)
* fixed that numeric casts with `COALESCE` could be off
* fixed invalid hit sorting (resulting in broken indexes and potential crashes)
  in some rare mappings-related cases
* fixed that (network) autokill did not remove enqueued jobs waiting on threads
* fixed missing error checks on certain attribute index insertion errors
* fixed that disk segment warnings on `UPDATE` were logged to stdout only
  (promoted them to errors)
* fixed expression evaluation vs outer `ORDER BY` vs distributed index issues
* fixed that `OPTIMIZE` could sometimes lose attribute indexes
* fixed 20+ crashes and major bugs in various rare/complicated edge cases

### Version 3.4.1, 09 jul 2021

New features:

* completely refactored our text processing pipeline (morphology etc), added
  [`mappings`](#using-mappings) and [`morphdict`](#using-morphdict) directives
  that replace now-deprecated `wordforms`
* added 2 new [phrase decay based](#phrase_decay10) based ranking signals
* added 6 new [trigram based](#ranking-trigrams) ranking signals, and indexing
  time Bloom filters that enable those
* added [attribute index support for MVA columns](#using-attribute-indexes)
* added query auto-kill on client disconnect (only in `thread_pool` mode), see
  the [network internals](#client-disconnects) section
* added fixed-size arrays support to [`DOT()` function](#dot-function)
* added [`SHOW INDEX FROM`](#show-index-from-syntax) statement to examine
  attribute indexes
* added support for `BETWEEN` as in `(expr BETWEEN <min> AND <max>)` syntax to
  [`SELECT`](#select-syntax)
* added [`SHOW INTERNAL STATUS`](#show-status-syntax) mode to `SHOW STATUS`
  statement to observe any experimental, not-yet-official internal counters
* added `killed_queries` and `local_XXX` counters (such as `local_disk_mb`,
  `local_docs`, etc) to [`SHOW STATUS`](#show-status-syntax) statement.
* added `--profile` switch to `indexer` (initially for SQL data sources only)

Deprecations:

* deprecated `wordforms` directive, see [`mappings`](#using-mappings)
* deprecated `INT` and `INTEGER` types in SphinxQL, use `UINT` instead
* deprecated `OPTION idf`, [IDFs are now unified](#how-sphinx-computes-idf)
* removed legacy `FACTORS()` output format, always using JSON now
* removed support for embedded stopwords hashes (deprecated since v.3.2),
  indexes with those will now fail to load

Changes and improvements:

* changed [IDFs to use unified unscaled range](#how-sphinx-computes-idf), so now
  they are (basically) computed as `idf = min(log(N/n), 20.0)`
* added UDF versioning, `searchd` now also attempts loading `myudf.so.VER` if
  `myudf.so` fails (this helps manage UDF API version mismatches)
* added automatic `ranker=none` when `WEIGHT()` is not used, to skip ranking and
  improve performance (note that this does not affect SphinxQL queries at all,
  but some legacy SphinxAPI queries might need slight changes)
* improved double value formatting, mostly in SphinxQL and/or JSON output
* improved multi-index searches, all local indexes must be unique now, and a few
  locking issues were fixed
* improved that siege mode now computes per-local-shard limits more precisely
* increased [`mappings`](#using-mappings) line size limit from ~750 bytes to 32K
* optimized queries vs indexes with many static attributes, 1.15x faster on
  250-column synthetic test, 3-5% savings in our prod
* optimized `atc` signal (up to 3.2x faster in extreme stops-only test case)
* optimized `ZONE` searches (up to 3x faster on average, 50x+ in extreme cases)
* optimized indexing about 3-5% with a few small internal optimizations
* disabled query cache by default
* disabled arithmetic and other inapplicable operations over array attributes

Fixes:

* fixed overlong (40+ chars) tokens handling in phrases and similar operators
* fixed error handling for UDFs that return `STRING`
* fixed that RT RAM flush could occasionally drop JSON attribute index(es)
* fixed missing dict fileinfos after `ATTACH` and a subsequent flush
* fixed `GEODIST()` vs extreme argument value deltas
* fixed that searches failed to access docstore after plain-to-RT `ATTACH`
* fixed `exact_hit` signal calculations vs non-ranked fields
* fixed pretty-printing in pure distributed case (for `FACTORS()`, JSON, etc)
* fixed that template index name was not properly reported in errors/warnings
* fixed `SHOW PROFILE` within multi-statement requests
* fixed attribute indexes on signed columns
* fixed that `DESCRIBE` only printed out one attribute index per column
* fixed a race and a crash in `SHOW TABLES`
* fixed `FACTORS()` vs missing `MATCH()` crash
* fixed a rare crash in token len calculation
* fixed a number of leaks and races

### Version 3.3.1, 06 jul 2020

New features:

* added [UDF call batching](#udf-call-batching) that enables UDFs to process
  multiple matched rows at a time
* added [`PP()`](#pp-function) pretty-printing function for `FACTORS()` and
  JSON values
* added multi-threaded index loading
* added [`KILL <tid>`](#kill-syntax) SphinxQL statement
* added [`SHOW INDEX <idx> AGENT STATUS`](#show-index-agent-status-syntax)
  SphinxQL statement, and moved per-agent counters there from `SHOW STATUS`

Minor new additions:

* added a number of runtime [server variables](#server-variables-reference) to
  [`SHOW VARIABLES`](#show-variables-syntax), namely
  * added `log_debug_filter`, `net_spin_msec`, `query_log_min_msec`,
    `sql_fail_filter`, and `sql_log_file`
  * moved `attrindex_thresh`, `siege_max_fetched_docs`, `siege_max_query_msec`,
    `qcache_max_bytes`, `qcache_thresh_msec`, and `qcache_ttl_sec` from
    `SHOW STATUS`
* added support for `SET GLOBAL server_var` in `sphinxql_state` startup script

Changes and improvements:

* removed `timestamp` columns support, use `uint` type instead (existing indexes
  are still supported; `timestamp` should automatically work as `uint` in those)
* removed `OPTION idf` and unified IDF calculations, see
  ["How Sphinx computes IDF"](#how-sphinx-computes-idf)
* changed `WEIGHT()` from integer to float
* changed `global_idf` behavior; now missing terms get local IDF instead of zero
* changed `OPTION cutoff` to properly account all processed matches
* changed directives deprecated in v.3.1 and earlier to hard errors
* optimized indexing a little (about 1-2% faster)
* optimized `DOT()` over `int8` vectors, up to 1.3x faster
* optimized query throughput on fast read-only queries up to 350+ Krps (various
  internal locking and performance changes, aka "highload optimizations")
* improved float value formatting, mostly in SphinxQL output
* improved `UPDATE` handling, updates can now execute in parallel (again)
* improved index schema checks (more checks for invalid names, etc)
* increased `SHOW THREADS` query limit from 512 to 2048 bytes

Fixes:

* fixed UDF memory leak when using a `FACTORS()` argument, and optimized that
  case a little
* fixed `sql_log_file` race that caused (rare-ish) crashes under high query load
* fixed that facets with expressions could occasionally yield either missing or
  incorrect resulting rows
* fixed an overflow in docid hash (triggered on rather huge indexes)
* fixed that `CALL KEYWORDS` did not use normalized term on `global_idf` lookup
* fixed expression types issue when doing mixed int/float const promotion
* fixed that RAM segments did not account the docid hash size
* fixed that `INSERT` only checked RAM segments for duplicate docids
* fixed an internal error on `COUNT(*)` vs empty RT

### Version 3.2.1, 31 jan 2020

New features:

* added [term-OR operator](#term-or-operator) for proper query-level synonyms,
  for example `(red || green || blue) pixel`
* added [document-only wordforms](#document-only-mappings), for example
  `!indexme => differently`
* added several [vector search](#searching-vector-searches) improvements
  * added int8/int/float fixed-width [array attributes](#using-array-attributes)
    support, for example `sql_attr_int8_array = myvec[128]`
  * added [`DOT()`](#dot-function) support for all those new array types
  * added int8 vectors support to JSON, and `int8[]` and `float[]`
    [JSON syntax extensions](#using-json)
  * added [`FVEC(json.field)`](#fvec-function) support to expressions, and
    the respective `SPH_UDF_TYPE_FLOAT_VEC` support to UDFs
* added [`BULK UPDATE`](#bulk-update-syntax) SphinxQL statement
* added attribute index reads for multi-GEODIST-OR queries, up to 15x+ speedup
  (see section on [geosearches](#searching-geosearches) for details)
* added [siege mode](#siege-mode), temporary global query limits with
  `SET GLOBAL siege`
* added `sum_idf_boost`, `is_noun_hits`, `is_latin_hits`, `is_number_hits`,
  `has_digit_hits` per-field ranking factors](#ranking-factors)
* added `is_noun`, `is_latin`, `is_number`, and `has_digit` per-term flags; added
  the respective `is_noun_words`, `is_latin_words`, `is_number_words`, and
  `has_digit_words` per-query ranking factors; and added query factors support
  to UDFs (see `sphinxudf.h`)
* added online query stream filtering with
  [`SET GLOBAL sql_fail_filter`](#sql_fail_filter-variable)
* added online query stream logging with
  [`SET GLOBAL sql_log_file`](#sql_log_file-variable)
* added [`SLICEAVG`, `SLICEMAX`, `SLICEMIN`](#slice-functions) functions, and
  [`STRPOS(str,conststr)`](#strpos-function) function

Minor new additions:

* added hash-comment support to `exceptions` files
* added `--dummy <arg>` switch to `searchd` (useful to quickly identify specific
  instances in the process list)
* added IDF info, term flags, and JSON format output to `CALL KEYWORDS` (for
  JSON output, call it with `CALL KEYWORDS(..., 1 AS json)`
* added `IS NULL` and `IS NOT NULL` checks to `ALL()` and `ANY()` JSON iterators
* added `last_good_id` to TSV indexing error reporting
* added `ram_segments` counter to `SHOW INDEX STATUS`, and renamed two counters
  (`ram_chunk` to `ram_segments_bytes`, `disk_chunks` to `disk_segments`)
* added `sql_query_kbatch` directive, deprecated `sql_query_killlist` directive
* added `<sphinx:kbatch>` support to XML source
* documented a few semi-hidden options (`net_spin_msec` for example)

Changes and improvements:

* improved parsing of long constant lists in expressions, requires much less
  `thread_stack` now
* improved `stopwords` handling, fixed the hash collisions issue
* improved `stopwords` directive, made it multi-valued
* improved `global_idf` handling, made global IDFs totally independent from
  per-index DFs
* improved `EXPLAIN`, ensured that it always reports real query plan and stats
* improved stats precision output for query times under 1 msec, and generally
  increased internal query timing precision
* improved argument types checking in expressions, and fixed a bunch of missed
  cases (issues on `GEODIST()` vs JSON, crash in `COALESCE()` args check, etc)
* improved `FACET` handling, single-search optimization must now always engage
* changed `indexer --nohup` to rename index files to `.new` on success
* changed `query_time` metric behavior for distributed indexes, now it will
  account wall time
* removed "search all indexes" syntax leftovers that were possible via SphinxAPI
* removed umask on `searchd.log`

Major optimizations:

* optimized frequent 1-part and 2-part `ORDER BY` clauses, up to 1.1x speedup
* optimized full scan queries, up to 1.2x+ speedup
* optimized `DOT()` for a few cases like `int8` vectors, up to 2x+ speedup
* optimized facets, up to 1.1x speedup

Fixes:

* fixed that `ORDER BY RAND()` was breaking `WEIGHT()` (also, enabled it for
  grouping queries)
* fixed hash-comment syntax in wordforms
* fixed a couple races in wordforms
* fixed a couple deadlocks related to `ATTACH`
* fixes a few issues with `max_window_hits()` and `exact_order` factors
* fixed a rare B-tree crash when inserting duplicate values
* fixed a rare TSV indexing issue (well-formed file could fail indexing because
  of a very rare buffer boundary issue)
* fixed occasional crashes on distributed searches on some CPU and glibc combos
  (double release)
* fixed incorrect `SHOW META` after index-less `SELECT`
* fixed `ALL()` and `ANY()` vs optimized JSON vectors, and fixed optimized
  int64 JSON vector accessor
* fixed that `SHOW THREADS ... OPTION columns=X` limit permanently clipped
  the thread descriptions
* fixed `/searchd` HTTP endpoint error format
* fixed per-index query stats vs RT indexes
* fixed that query parser could occasionally fail on high ASCII codes
* fixed a few issues causing incorrect or unexpected handling of `cutoff` and
  other query limits
* fixed a few `json_packed_keys` issues
* fixed MVA64 values clipping on `INSERT`
* fixed occasional crashes and/or memory corruption on `UPDATE` and `INSERT`
* fixed `SNIPPET(field,QUERY())` case to some extent (we now filter out query
  syntax and treat `QUERY()` as a bag of words in this case)
* fixed that index reads on JSON in RT could erroneously disable other `WHERE`
  conditions from the query
* fixed a number of facets-related issues (occasionally non-working parallel
  execution, occasional crashes, etc)
* fixed a crash on empty index list via SphinxAPI
* fixed schema attributes order for XML/TSV/CSV sources
* fixed sticky `regexp_filter` vs `ATTACH`

### Version 3.1.1, 17 oct 2018

* added `indexer --dump-rows-tsv` switch, and renamed `--dump-rows` to
  `--dump-rows-sql`
* added initial `COALESCE()` function support for JSONs (beware that it will
  compute everything in floats!)
* added support for `!=`, `IN`, and `NOT IN` syntax to expressions
* added `prefix_tokens` and `suffix_tokens` options to `blend_mode` directive
* added `OPTION rank_fields`, lets you specify fields to use for ranking with
  either expression or ML (UDF) rankers
* added explicit duplicate documents (docids) suppression back into `indexer`
* added `batch_size` variable to `SHOW META`
* added `csvpipe_header` and `tsvpipe_header` directives
* added `sql_xxx` counters to `SHOW STATUS`, generally cleaned up counters
* added mixed codes indexing, available via `blend_mixed_codes` and
  `mixed_codes_fields` directives
* added `OPTION inner_limit_per_index` to explicitly control reordering in
  a nested sharded select
* added a hard limit for `max_matches` (must be under 100M)
* optimized Postgres indexing CPU and RAM use quite significantly
* optimized `FACET` queries with expressions and simple by-attribute
  (no aliases!) facets; multi-sort optimization now works in that case
* optimized `id` lookups (queries like `UPDATE ... WHERE id=123` should now be
  much faster)
* optimized result set aggregation vs nested sharded selects
* optimized `PACKEDFACTORS()` storage a lot (up to 60x speedup with
  `max_matches=50000`)
* improved UDF error handling, the error argument is now a message buffer
  instead of just a 1-char flag
* improved the nested sharded select reordering, less confusing now (by default,
  does *not* scale the inner `LIMIT` anymore)
* improved `searchd --listen` switch, multiple `--listen` instances are now
  allowed, and `--console` is *not* required anymore
* improved failed allocation reporting, and added huge allocation tracking
* removed legacy `@count`, `@weight`, `@expr`, `@geodist` syntax support
* removed legacy `SetWeights()`, `SetMatchMode()`, `SetOverride()`,
  `SetGeoAnchor()` calls, `SPH_MATCH_xxx` constants, and `SPH_SORT_EXPR`
  sorting mode from APIs
* removed legacy `spelldump` utility
* removed unused `.sha` index files
* removed extraneous "no extra index definitions" warning

Major fixes:

* fixed 9+ crashes caused by certain complex (and usually rare) conditions
  and/or settings combinations
* fixed 2 crashes caused by broken index data (in vrows and dictionaries)
* fixed plain index locking issues on Windows
* fixed JSON fields handling vs strings and NULLs (no more corner cases like
  NULL objects passing a test for json.col=0)
* fixed matches loss issue in positional (phrase/order/sentence etc) operators
  and modifiers under certain conditions
* fixed hashing-related hangups under certain (rather rare) occasions
* fixed several type inference issues in expressions when using JSON fields

Other fixes:

* fixed that `min_best_span_pos` was sometimes off
* fixed the behavior on missing `global_idf` file
* fixed `indextool --check` vs string attributes, and vs empty JSONs
* fixed blended vs multiforms behavior (works much more predictably now)
* fixed query parser vs wildcard-only tokens
* fixed that MySQL 8.0+ clients failed to connect
* fixed occasional semaphore races on startup
* fixed `OPTIMIZE` vs `UPDATE` race; `UPDATE` can now fail with a timeout
* fixed `indexer --merge --rotate` vs kbatches
* fixed occasional rotation-related deadlock
* fixed a few memory leaks

### Version 3.0.3, 30 mar 2018

* added `BITCOUNT()` function and bitwise-NOT operator, eg `SELECT BITCOUNT(~3)`
* made `searchd` config section completely optional
* improved `min_infix_len` behavior, required 2-char minimum is now enforced
* improved docs, added a few sections
* fixed binary builds performance
* fixed several crashes (related to docstore, snippets, threading,
  `json_packed_keys` in RT)
* fixed docid-less SQL sources, forbidden those for now (docid still required)
* fixed int-vs-float precision issues in expressions in certain cases
* fixed `uptime` counter in `SHOW STATUS`
* fixed query cache vs `PACKEDFACTORS()`

### Version 3.0.2, 25 feb 2018

* added `full_field_hit` ranking factor
* added `bm15` ranking factor name (legacy `bm25` name misleading,
  to be removed)
* optimized RT inserts significantly (up to 2-6x on certain benchmarks vs 3.0.1)
* optimized `exact_field_hit` ranking factor, impact now negligible
  (approx 2-4%)
* improved `indexer` output, less visual noise
* improved `searchd --safetrace` option, now skips `addr2line` to avoid
  occasional freezes
* improved `indexer` MySQL driver lookup, now also checking for `libmariadb.so`
* fixed rare occasional `searchd` crash caused by attribute indexes
* fixed `indexer` crash on missing SQL drivers, and improved error reporting
* fixed `searchd` crash on multi-index searches with docstore
* fixed that expression parser failed on field-shadowing attributes in
  `BM25F()` weights map
* fixed that `ALTER` failed on field-shadowing attributes vs
  `index_field_lengths` case
* fixed junk data writes (seemingly harmless but anyway) in certain cases
* fixed rare occasional `searchd` startup failures (threading related)

### Version 3.0.1, 18 dec 2017

* first public release of 3.x branch


Changes since v.2.x
--------------------

> WIP: the biggest change to rule them all is yet to come. The all new, fully
RT index format is still in progress, and not yet available. Do not worry, ETL
via `indexer` will *not* be going anywhere. Moreover, despite being fully and
truly RT, the new format is actually already *faster* at batch indexing.

The biggest changes since Sphinx v.2.x are:

  * added DocStore, document storage
    * original document contents can now be stored into the index
    * disk based storage, RAM footprint should be minimal
    * goodbye, *having* to query Another Database to fetch data
  * added new attributes storage format
    * arbitrary updates support (including MVA and JSON)
    * goodbye, sudden size limits
  * added attribute indexes, with JSON support
    * ... `WHERE gid=123` queries can now utilize A-indexes
    * ... `WHERE MATCH('hello') AND gid=123` queries can now efficiently
      intersect FT-indexes and A-indexes
    * goodbye, *having* to use fake keywords
  * added compressed JSON keys
  * switched to rowids internally, and forced all docids to 64 bits

Another two big changes that are already available but still in pre-alpha are:

  * added "zero config" mode (`./sphinxdata` folder)
  * added index replication

The additional smaller niceties are:

  * added always-on support for xmlpipe, snowball stemmers, and re2
    (regexp filters)
  * added `blend_mode=prefix_tokens`, and enabled empty `blend_mode`
  * added `kbatch_source` directive, to auto-generate k-batches from source
    docids (in addition to explicit queries)
  * added `SHOW OPTIMIZE STATUS` statement
  * added `exact_field_hit` ranking factor
  * added `123.45f` value syntax in JSON, optimized support for float32 vectors,
    and `FVEC()` and `DOT()` functions
  * added preindexed data in document storage to speed up `SNIPPETS()`
    (via `hl_fields` directive)
  * changed field weights, zero and negative weights are now allowed
  * changed stemming, keywords with digits are now excluded

A bunch of legacy things were removed:

  * removed `dict`, `docinfo`, `infix_fields`, `prefix_fields` directives
  * removed `attr_flush_period`, `hit_format`, `hitless_words`, `inplace_XXX`,
    `max_substring_len`, `mva_updates_pool`, `phrase_boundary_XXX`,
    `sql_joined_field`, `subtree_XXX` directives
  * removed legacy id32 and id64 modes, mysqlSE plugin, and
    `indexer --keep-attrs` switch

And last but not least, the new config directives to play with are:

  * `docstore_type`, `docstore_block`, `docstore_comp` (per-index) and
    `docstore_cache_size` (global) let you generally configure DocStore
  * `stored_fields`, `stored_only_fields`, `hl_fields` (per-index) let you
    configure what to put in DocStore
  * `kbatch`, `kbatch_source` (per-index) update the legacy k-lists-related
    directives
  * `updates_pool` (per-index) sets vrow file growth step
  * `json_packed_keys` (`common` section) enables the JSON keys compression
  * `binlog_flush_mode` (`searchd` section) changes the per-op flushing mode
    (0=none, 1=fsync, 2=fwrite)

Quick update caveats:

  * if you were using `sql_query_killlist` then you now *must* explicitly
    specify `kbatch` and list all the indexes that the k-batch should be
    applied to:

```bash
sql_query_killlist = SELECT deleted_id FROM my_deletes_log
kbatch = main

# or perhaps:
# kbatch = shard1,shard2,shard3,shard4
```

References
-----------

Nothing to see here, just a bunch of Markdown links that are too long to inline!

[1]:https://trec.nist.gov/pubs/trec13/papers/microsoft-cambridge.web.hard.pdf
[2]:#indexing-special-chars-blended-tokens-and-mixed-codes


Copyrights
-----------

This documentation is copyright (c) 2017-2024, Andrew Aksyonoff. The author
hereby grants you the right to redistribute it in a verbatim form, along with
the respective copy of Sphinx it came bundled with. All other rights are
reserved.
