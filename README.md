# Kirby Database Storage Plugin

## Installation

### Download

Download and copy this repository to `/site/plugins/database-storage`.

### Composer

```
composer require getkirby/database-storage
```

### Git submodule

```
git submodule add https://github.com/getkirby/database-storage.git site/plugins/database-storage
```

## How it works?

### Setting up a database

For this example, we are creating a new SQLite database in `/site/db` and call it `comments.sqlite`. But you can place it everywhere you like and then later change the path in the config. (see below)

#### Required fields

Our database models require a couple core fields to work correctly:

| Field name | Type | Config
| - | - | - |
| id | INTEGER | primary key, autoincrement, not null, unique
| title | TEXT |
| slug | TEXT | not null
| uuid | TEXT | not null
| created | TEXT | not null, default: CURRENT_TIMESTAMP
| modified | TEXT | not null, default: CURRENT_TIMESTAMP
| version | TEXT | not null
| language | TEXT | not null
| parent | TEXT |
| template | TEXT |
| num | INTEGER | default: NULL
| lock | TEXT |
| draft | INTEGER | not null, default: 1

SQLite has a very limited set of column types. You might want to choose more appropriate types for MySQL or other databases.

Once all those columns are in place, you can add your own custom columns for custom fields. For our comments example, we will create a `text` and an `email` column.

Here's a full SQL query to create our comments database.

```
CREATE TABLE "comments" (
"id" INTEGER UNIQUE NOT NULL PRIMARY KEY ASC AUTOINCREMENT,
"title" TEXT,
"slug" TEXT NOT NULL,
"uuid" TEXT NOT NULL,
"created" TEXT DEFAULT CURRENT_TIMESTAMP NOT NULL,
"modified" TEXT DEFAULT CURRENT_TIMESTAMP NOT NULL,
"version" TEXT NOT NULL,
"language" TEXT NOT NULL,
"parent" TEXT,
"template" TEXT,
"num" INTEGER DEFAULT NULL,
"lock" TEXT,
"draft" INTEGER DEFAULT 1 NOT NULL
"text" TEXT,
"email" TEXT
);
```

#### Create a table using the Kirby CLI

If you are using the Kirby CLI, you can create a new table via the `table:create` command:

````
kirby table:create
```

You will be asked to specify the database connection, the table name and the list of custom fields.

You can also provide database connection and table name immediately via arguments:

````
kirby table:create myDatabase myTable
```

`myDatabase` is referencing the config `key` for the database connection defined in your config (see below)

### Database Connection

We need to define the connection to our database in the `/site/config/config.php` under the `database` key. The name for the connection can be defined by you, but needs to match later with the `DATABASE_NAME` constant in our page model (see below)

```php
<?php

use Kirby\Database\Database;

return [
    'database' => [
        'comments' => new Database([
            'type'     => 'sqlite',
            'database' => dirname(__DIR__) . '/db/comments.sqlite'
        ])
    ]
];
```

### Parent page

First, create a regular Kirby page that serves as the parent for your database pages. For our comments example, we create a new content folder called `/content/comments` with a text file called `comments.txt`. This will connect the page to a new `comments.php` template and – more importantly – a new `CommentsPage` model. This model is the key to load our child pages from the database (see the setup below)

### Models

For the new comments page, the model will use the `HasDatabaseChildren` trait from our plugin. This trait will replace the `$page->children()` method and load children from our database. All we need to do is to define the child model with the `DATABASE_CHILD_MODEL` constant.

**`/site/models/comments.php`**
```php
<?php

use Kirby\Cms\Page;
use Kirby\DatabaseStorage\HasDatabaseChildren;

class CommentsPage extends Page
{
    use HasDatabaseChildren;

    public const DATABASE_CHILD_MODEL = CommentPage::class;
}
```

**`/site/models/comment.php`**

Each child of the comments page will now use a new `CommentPage` model and that model needs to extend the `DatabasePage` class from the plugin to make everything work. This class will overwrite all the page action methods to create, update and delete pages in the database and no longer on disk.

The constants in the model finalize our setup. `DATABASE_NAME` refers to our config setting (`database.comments`), `DATABASE_TABLE` needs to match the table name in our database and the `DATABASE_FIELDS` array defines all custom fields that are stored in the table. The required core fields (see above) are not included here.

```php
<?php

use Kirby\DatabaseStorage\DatabasePage;

class CommentPage extends DatabasePage
{
    public const DATABASE_NAME = 'comments';
    public const DATABASE_TABLE = 'comments';
    public const DATABASE_FIELDS = [
        'text',
        'email',
    ];
}
```

## What’s Kirby?

- **[getkirby.com](https://getkirby.com)** – Get to know the CMS.
- **[Try it](https://getkirby.com/try)** – Take a test ride with our online demo. Or download one of our kits to get started.
- **[Documentation](https://getkirby.com/docs/guide)** – Read the official guide, reference and cookbook recipes.
- **[Issues](https://github.com/getkirby/kirby/issues)** – Report bugs and other problems.
- **[Feedback](https://feedback.getkirby.com)** – You have an idea for Kirby? Share it.
- **[Forum](https://forum.getkirby.com)** – Whenever you get stuck, don't hesitate to reach out for questions and support.
- **[Discord](https://chat.getkirby.com)** – Hang out and meet the community.
- **[Mastodon](https://mastodon.social/@getkirby)** – Follow us in the Fediverse.
- **[Bluesky](https://bsky.app/profile/getkirby.com)** – Follow us on Bluesky.

---

## License

MIT

## Credits

- [Kirby Team](https://getkirby.com)
