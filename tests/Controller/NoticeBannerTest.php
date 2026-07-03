<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Notice\NoticeType;
use App\Tests\FixturesWebTestCase;

class NoticeBannerTest extends FixturesWebTestCase
{
    public function testNoticeShowsInTypeColour(): void
    {
        $anne = $this->user('anne@trepiedi.test');
        $anne->setNotice('Let op: je inleg is nog niet binnen.')->setNoticeType(NoticeType::Warning);
        $this->em->flush();

        $this->client->loginUser($anne);
        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $alert = $crawler->filter('[data-account-notice]');
        $this->assertCount(1, $alert, 'De melding hoort als banner te verschijnen.');
        $this->assertStringContainsString('alert-warning', $alert->attr('class'), 'Waarschuwing = oranje.');
        $this->assertStringContainsString('Let op: je inleg is nog niet binnen.', $alert->text());
        $this->assertStringStartsWith($anne->getNoticeSignature() . '-', $alert->attr('data-notice-key'));
    }

    public function testInfoNoticeIsGreenAndErrorIsRed(): void
    {
        $anne = $this->user('anne@trepiedi.test');

        $anne->setNotice('Veel succes!')->setNoticeType(NoticeType::Info);
        $this->em->flush();
        $this->client->loginUser($anne);
        $crawler = $this->client->request('GET', '/');
        $this->assertStringContainsString('alert-success', $crawler->filter('[data-account-notice]')->attr('class'), 'Info = groen.');

        $anne->setNoticeType(NoticeType::Error);
        $this->em->flush();
        $crawler = $this->client->request('GET', '/');
        $this->assertStringContainsString('alert-danger', $crawler->filter('[data-account-notice]')->attr('class'), 'Fout = rood.');
    }

    public function testNoBannerWhenNoticeEmpty(): void
    {
        $this->client->loginUser($this->user('anne@trepiedi.test'));
        $crawler = $this->client->request('GET', '/');

        $this->assertCount(0, $crawler->filter('[data-account-notice]'), 'Zonder tekst geen banner.');
    }

    public function testBlankNoticeIsStoredAsNull(): void
    {
        $anne = $this->user('anne@trepiedi.test');
        $anne->setNotice("   \n  ");

        $this->assertNull($anne->getNotice(), 'Alleen witruimte telt als geen melding.');
        $this->assertFalse($anne->hasNotice());
    }

    public function testAdminCanSetNoticeViaForm(): void
    {
        $id = $this->user('anne@trepiedi.test')->getId();

        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $crawler = $this->client->request('GET', '/admin/deelnemers/' . $id . '/bewerken');

        $form = $crawler->selectButton('Opslaan')->form();
        $form['user_admin[notice]'] = 'Welkom terug in de poule!';
        $form['user_admin[noticeType]'] = 'error';
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/deelnemers');

        $this->em->clear();
        $updated = $this->em->getRepository(User::class)->find($id);
        $this->assertSame('Welkom terug in de poule!', $updated->getNotice());
        $this->assertSame(NoticeType::Error, $updated->getNoticeType());
    }
}
