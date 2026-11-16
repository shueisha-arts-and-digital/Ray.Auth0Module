# Ray.Auth0Module

## Installation

### Composer install

    $ composer require ray/auth0-module

### Module install

```php
use Ray\Di\AbstractModule;
use Ray\Auth0Module\Auth0Module;

class AppModule extends AbstractModule
{
    protected function configure()
    {
        $this->install(new Auth0Module([
            'domain' => getenv('AUTH0_DOMAIN'),
            'clientId' => getenv('AUTH0_MANAGEMENT_CLIENT_ID'),
            'clientSecret' => getenv('AUTH0_MANAGEMENT_CLIENT_SECRET'),
            'audience' => [getenv('AUTH0_AUDIENCE')],
        ]));
    }
}
```

> **Note:** `cookieSecret`（`AUTH0_MANAGEMENT_COOKIE_SECRET`）は不要になりました。本モジュールは Auth0-PHP SDK の `api` / `management` / `none` strategy を使用しており、暗号化セッション Cookie の読み書きは行いません。既存の設定に `cookieSecret` が含まれていても無視されるだけで実害はありません。

## Configuration

| キー | 必須 | 使われる箇所 | 説明 |
|---|---|---|---|
| `domain` | ○ | Management / Auth / Authentication | Auth0 テナントのドメインです。`example.auth0.com`、`https://example.auth0.com`、`example.auth0.com/` のいずれの書式でも、Management 用にはホスト名へ正規化します。 |
| `clientId` | ○ | Management / Authentication | Management API を呼び出す M2M アプリケーションの Client ID です。 |
| `clientSecret` | ○ | Management / Authentication | 同じく Client Secret です。 |
| `audience` | ○ | Auth | `Auth::verifyToken()` で、アクセストークンの `aud` を検証するための値です。**Management では使いません。** |
| `customDomain` | | Auth / Authentication | カスタムドメインを使う場合に指定します（任意）。`Auth` はトークンの issuer の検証と JWKS の取得に、`Authentication` は要求先 URL に使います。**Management では使いません。** |
| `tokenCache` | | Management / Auth | `Psr\Cache\CacheItemPoolInterface` の実装です（任意）。 |

### `audience` について

config の `audience` は、媒体が Auth0 に登録した自前の API の識別子です。
受け取ったユーザーのアクセストークンを検証するために使います。
Management API 用のトークンを取得するときの audience は `https://{domain}/api/v2/` に決まっており、SDK が自動で補います。
そのため、config の `audience` は Management には渡しません。

### `tokenCache` について

`tokenCache` を渡すと、Management API 用のアクセストークンをキャッシュに保存し、有効期限まで使い回します。
渡さない場合、トークンは `Management` のインスタンス内にだけ保持されます。
`Management` は SINGLETON で束縛しているため、1 プロセス（1 リクエスト）の中では 1 回だけ取得します。
リクエストをまたいで使い回すには、APCu や Redis などの PSR-6 実装を渡してください。

同じプールは `Auth` にも渡り、`Auth::verifyToken()` が取得する JWKS のキャッシュにも使われます。
キーは別々なので衝突しません。

### 例外

`domain` / `clientId` / `clientSecret` が欠けている、空文字である、文字列でない場合や、`tokenCache` が PSR-6 の実装でない場合は、`Management` の生成時に `Ray\Auth0Module\Exception\InvalidConfiguration`（`InvalidArgumentException` の派生）が投げられます。

## Usage

```php
use Auth0\SDK\API\Management\Management;
use Auth0\SDK\API\Management\Users\Requests\CreateUserRequestContent;
use BEAR\Resource\ResourceObject;

class User extends ResourceObject
{
    public function __construct(
        private Management $management,
    ) {
    }

    public function onPost(string $email, string $name, string $initialPassword) : static
    {
        // 4xx / 5xx の応答は Auth0ApiException になります
        $user = $this->management->users->create(new CreateUserRequestContent([
            'connection' => 'Username-Password-Authentication',
            'email' => $email,
            'emailVerified' => true,
            'name' => $name,
            'password' => $initialPassword,
        ]));

        $this->body = ['user_id' => $user?->getUserId()];

        return $this;
    }
}
```

See more at https://github.com/auth0/Auth0-PHP

## Upgrading from 5.x to 6.0.0

6.0.0 は auth0/auth0-php 9.x に対応しました。
Management API のクライアントが SDK 側で作り直されたため、利用側の書き換えが必要です。

### 1. 型宣言を変更します

注入する `Management` の型が変わりました。

```diff
-use Auth0\SDK\API\Management;
+use Auth0\SDK\API\Management\Management;
```

`Auth0\SDK\API\Management`（旧クラス）での型宣言は、束縛がないため注入できません。

### 2. Management API の呼び出しを書き換えます

| 項目 | 5.x（auth0-php 8.x） | 6.0.0（auth0-php 9.x） |
|---|---|---|
| リソースの指定 | メソッド呼び出し `$mgmt->users()` | プロパティ `$mgmt->users` |
| 一覧取得 | `getAll()` | `list()` |
| リクエストの渡し方 | 連想配列（snake_case のキー） | 型付きオブジェクト（例: `new CreateUserRequestContent([...])`、camelCase のキー） |
| 戻り値 | PSR-7 の `ResponseInterface` | 型付きの PHP オブジェクト（例: `$user->getEmail()`） |
| 戻り値の処理 | `HttpResponse::decodeContent()` で配列に変換 | 変換は不要（`decodeContent()` に渡すと失敗します） |
| 成否の判定 | `HttpResponse::wasSuccessful()` で確認 | 4xx / 5xx で例外が投げられます |
| エラー時の例外 | 利用側で独自に投げる | `Auth0ApiException`（API のエラー応答）、`Auth0Exception`（SDK・通信のエラー） |
| ページネーション | `page` / `per_page` を指定して手動でループ | `Pager` が自動でページを送ります |

### 3. Management が読まなくなった config キーを確認します

Management が読むのは `domain` / `clientId` / `clientSecret` / `tokenCache` だけになりました。
次のキーは Management では無視されます。
ただし `audience` と `customDomain` は `Auth` / `Authentication` で従来どおり使われるため、**config から削除しないでください。**

- `audience`: Management API 用の audience は `https://{domain}/api/v2/` に固定されます。
- `customDomain`: Management API の呼び出しは常に `domain` に対して行います。
- その他の `SdkConfiguration` 用のキー（`strategy` など）

`domain` は、スキーム付き・末尾スラッシュ付きでもホスト名に正規化するため、書式の変更は不要です。

### 4. 例外の型の変化を確認します

| 場面 | 5.x | 6.0.0 |
|---|---|---|
| config の値が欠けている・不正 | `Auth0\SDK\Exception\ConfigurationException` | `Ray\Auth0Module\Exception\InvalidConfiguration`（`InvalidArgumentException` の派生） |
| Management API 用トークンの取得に失敗 | — | `RuntimeException`（`Auth0Exception` にはなりません） |
| API のエラー応答（4xx / 5xx） | 例外にならず、`ResponseInterface` で返る | `Auth0ApiException` |

### 5. Management のスコープが SINGLETON になりました

`Management` は SINGLETON で束縛されるようになり、注入箇所が複数あっても同じインスタンスが渡されます。
これにより、Management API 用のトークンは 1 プロセスで 1 回だけ取得されます。
インスタンスに状態を持たせる使い方をしている場合は注意してください。

### 6. （任意）`tokenCache` を設定します

リクエストをまたいでトークンを使い回すには、config に `tokenCache`（PSR-6）を追加します。
詳しくは「`tokenCache` について」を参照してください。
