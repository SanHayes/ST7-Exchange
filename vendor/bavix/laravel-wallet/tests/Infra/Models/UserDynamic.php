<?php

declare(strict_types=1);

namespace Bavix\Wallet\Test\Infra\Models;

use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Traits\HasWallet;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property string $email
 *
 * @method int getKey()
 */
final class UserDynamic extends Model implements Wallet
{
    use HasWallet;

    /**
     * @var array<int,string>
     */
    protected $fillable = ['name', 'email'];

    public function getTable(): string
    {
        return 'users';
    }

    public function getDynamicDefaultSlug(): string
    {
        return 'default-' . $this->email;
    }
}
