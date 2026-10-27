<?php

namespace Tests\Feature;

use App\Livewire\Settings\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * پروفایل مدیر (/admin/profile):
 * ویرایش نام نمایشی و ایمیل ورود + تغییر رمز عبور با تأیید رمز فعلی.
 */
class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'     => 'مدیر آزمون',
            'email'    => 'admin@profile.test',
            'password' => 'secret123',
            'role'     => 'admin',
            'status'   => 'active',
        ]);
    }

    /* -------------------------------------------------------------- */
    /*  دسترسی و رندر                                                  */
    /* -------------------------------------------------------------- */

    public function test_profile_page_renders_for_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.profile'))
            ->assertOk()
            ->assertSee('پروفایل مدیر')
            ->assertSee('اطلاعات حساب')
            ->assertSee('تغییر رمز عبور');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.profile'))->assertRedirect(route('login'));
    }

    /* -------------------------------------------------------------- */
    /*  اطلاعات حساب (نام + ایمیل)                                     */
    /* -------------------------------------------------------------- */

    public function test_admin_can_update_name_and_email(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Profile::class)
            ->assertSet('name', 'مدیر آزمون')
            ->assertSet('email', 'admin@profile.test')
            ->set('name', 'مدیر تازه')
            ->set('email', 'new@profile.test')
            ->call('saveProfile')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $fresh = $this->admin->fresh();

        $this->assertSame('مدیر تازه', $fresh->name);
        $this->assertSame('new@profile.test', $fresh->email);
    }

    public function test_email_is_normalized_to_lowercase_and_trimmed(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Profile::class)
            ->set('email', '  Mixed@Profile.TEST ')
            ->call('saveProfile')
            ->assertHasNoErrors();

        $this->assertSame('mixed@profile.test', $this->admin->fresh()->email);
    }

    public function test_email_must_be_unique(): void
    {
        User::create([
            'name'     => 'کاربر دیگر',
            'email'    => 'taken@profile.test',
            'password' => 'secret123',
            'role'     => 'operator',
            'status'   => 'active',
        ]);

        $this->actingAs($this->admin);

        Livewire::test(Profile::class)
            ->set('email', 'taken@profile.test')
            ->call('saveProfile')
            ->assertHasErrors(['email']);

        $this->assertSame('admin@profile.test', $this->admin->fresh()->email);
    }

    public function test_email_must_be_valid(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Profile::class)
            ->set('email', 'not-an-email')
            ->call('saveProfile')
            ->assertHasErrors(['email']);
    }

    public function test_name_is_required(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Profile::class)
            ->set('name', '')
            ->call('saveProfile')
            ->assertHasErrors(['name']);
    }

    /* -------------------------------------------------------------- */
    /*  تغییر رمز عبور                                                 */
    /* -------------------------------------------------------------- */

    public function test_admin_can_change_password(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Profile::class)
            ->set('current_password', 'secret123')
            ->set('password', 'newpass123')
            ->set('password_confirmation', 'newpass123')
            ->call('savePassword')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            // فیلدهای رمز بعد از ذخیره پاک می‌شوند
            ->assertSet('current_password', '')
            ->assertSet('password', '')
            ->assertSet('password_confirmation', '');

        $fresh = $this->admin->fresh();

        $this->assertTrue(Hash::check('newpass123', $fresh->password));
        $this->assertFalse(Hash::check('secret123', $fresh->password));
    }

    public function test_password_change_requires_correct_current_password(): void
    {
        $this->actingAs($this->admin);

        $oldHash = $this->admin->password;

        Livewire::test(Profile::class)
            ->set('current_password', 'wrong-password')
            ->set('password', 'newpass123')
            ->set('password_confirmation', 'newpass123')
            ->call('savePassword')
            ->assertHasErrors(['current_password']);

        $this->assertTrue(Hash::check('secret123', $this->admin->fresh()->password));
        $this->assertSame($oldHash, $this->admin->fresh()->password);
    }

    public function test_current_password_is_required(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Profile::class)
            ->set('password', 'newpass123')
            ->set('password_confirmation', 'newpass123')
            ->call('savePassword')
            ->assertHasErrors(['current_password']);
    }

    public function test_new_password_must_be_confirmed(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Profile::class)
            ->set('current_password', 'secret123')
            ->set('password', 'newpass123')
            ->set('password_confirmation', 'different999')
            ->call('savePassword')
            ->assertHasErrors(['password']);

        $this->assertTrue(Hash::check('secret123', $this->admin->fresh()->password));
    }

    public function test_new_password_must_have_min_length(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Profile::class)
            ->set('current_password', 'secret123')
            ->set('password', '123')
            ->set('password_confirmation', '123')
            ->call('savePassword')
            ->assertHasErrors(['password']);

        $this->assertTrue(Hash::check('secret123', $this->admin->fresh()->password));
    }

    public function test_changed_password_works_for_next_login(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Profile::class)
            ->set('current_password', 'secret123')
            ->set('password', 'newpass123')
            ->set('password_confirmation', 'newpass123')
            ->call('savePassword')
            ->assertHasNoErrors();

        $this->assertTrue(
            Hash::check('newpass123', $this->admin->fresh()->password)
        );
    }
}
