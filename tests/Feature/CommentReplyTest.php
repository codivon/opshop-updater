<?php

namespace Tests\Feature;

use App\Livewire\Comments\Index as CommentsIndex;
use App\Livewire\Shop\CommentSection;
use App\Models\Comment;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * پاسخ مدیر به دیدگاه‌ها — مدل + کامپوننت ادمین + نمایش فقط دیدگاه‌های تأییدشده در فروشگاه.
 */
class CommentReplyTest extends TestCase
{
    use RefreshDatabase, MakesShopData;

    protected function makeComment(Package $package, array $attrs = []): Comment
    {
        return Comment::create(array_merge([
            'commentable_type' => Comment::TYPE_PACKAGE,
            'commentable_id'   => $package->id,
            'name'             => 'کاربر مهمان',
            'body'             => 'دیدگاه تستی برای پاسخ مدیر.',
            'status'           => Comment::STATUS_PENDING,
            'ip'               => '127.0.0.1',
        ], $attrs));
    }

    /* ---------------- مدل ---------------- */

    public function test_model_can_save_and_remove_reply(): void
    {
        $comment = $this->makeComment($this->makePackage());

        $this->assertFalse($comment->hasReply());

        $comment->saveReply('پاسخ تست مدیر');

        $comment->refresh();
        $this->assertTrue($comment->hasReply());
        $this->assertSame('پاسخ تست مدیر', $comment->reply);
        $this->assertNotNull($comment->replied_at);

        $comment->removeReply();

        $comment->refresh();
        $this->assertFalse($comment->hasReply());
        $this->assertNull($comment->reply);
        $this->assertNull($comment->replied_at);
    }

    /* ---------------- کامپوننت ادمین ---------------- */

    public function test_admin_can_reply_via_component(): void
    {
        $comment = $this->makeComment($this->makePackage());

        Livewire::test(CommentsIndex::class)
            ->call('replyTo', $comment->id)
            ->assertSet('showReplyModal', true)
            ->assertSet('replyingId', $comment->id)
            ->set('replyText', 'سپاس از بازخورد شما؛ به‌زودی بررسی می‌شود.')
            ->call('saveReply')
            ->assertSet('showReplyModal', false);

        $comment->refresh();
        $this->assertSame('سپاس از بازخورد شما؛ به‌زودی بررسی می‌شود.', $comment->reply);
        $this->assertNotNull($comment->replied_at);
    }

    public function test_admin_reply_is_updated_on_second_save(): void
    {
        $comment = $this->makeComment($this->makePackage());
        $comment->saveReply('پاسخ اولیه');

        Livewire::test(CommentsIndex::class)
            ->call('replyTo', $comment->id)
            ->assertSet('replyText', 'پاسخ اولیه') // پاسخ فعلی در مودال پیش‌پر می‌شود
            ->set('replyText', 'پاسخ ویرایش‌شده')
            ->call('saveReply');

        $this->assertSame('پاسخ ویرایش‌شده', $comment->fresh()->reply);
    }

    public function test_reply_text_is_required_and_limited(): void
    {
        $comment = $this->makeComment($this->makePackage());

        Livewire::test(CommentsIndex::class)
            ->call('replyTo', $comment->id)
            ->set('replyText', '')
            ->call('saveReply')
            ->assertHasErrors(['replyText']);

        Livewire::test(CommentsIndex::class)
            ->call('replyTo', $comment->id)
            ->set('replyText', str_repeat('x', 2001))
            ->call('saveReply')
            ->assertHasErrors(['replyText']);

        $this->assertFalse($comment->fresh()->hasReply());
    }

    public function test_admin_can_remove_reply_via_component(): void
    {
        $comment = $this->makeComment($this->makePackage());
        $comment->saveReply('پاسخ برای حذف');

        Livewire::test(CommentsIndex::class)
            ->call('removeReply', $comment->id);

        $this->assertFalse($comment->fresh()->hasReply());
    }

    /* ---------------- نمایش در فروشگاه ---------------- */

    public function test_shop_section_shows_reply_only_on_approved_comments(): void
    {
        $package = $this->makePackage();

        $approved = $this->makeComment($package, [
            'status' => Comment::STATUS_APPROVED,
            'body'   => 'دیدگاه تأییدشده',
        ]);
        $approved->saveReply('پاسخ مدیر به تأییدشده');

        $pending = $this->makeComment($package, [
            'status' => Comment::STATUS_PENDING,
            'body'   => 'دیدگاه در انتظار',
        ]);
        $pending->saveReply('پاسخ مدیر به در انتظار');

        $component = Livewire::test(CommentSection::class, ['model' => $package, 'label' => 'پکیج']);
        $approvedList = $component->instance()->approved()->items();

        $this->assertCount(1, $approvedList);
        $this->assertSame($approved->id, $approvedList[0]->id);
        $this->assertTrue($approvedList[0]->hasReply());
    }
}
