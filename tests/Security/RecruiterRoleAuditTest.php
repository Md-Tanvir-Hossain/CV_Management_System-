<?php

namespace App\Tests\Security;

use App\Entity\Attribute;
use App\Entity\CV;
use App\Entity\CvLike;
use App\Entity\DiscussionPost;
use App\Entity\Position;
use App\Entity\PositionAccessRule;
use App\Entity\PositionAttribute;
use App\Entity\ProfileAttributeValue;
use App\Entity\User;
use App\Enum\AccessRuleOperator;
use App\Enum\AttributeCategory;
use App\Enum\AttributeType;
use App\Repository\AttributeRepository;
use App\Repository\CvLikeRepository;
use App\Repository\CvRepository;
use App\Repository\DiscussionPostRepository;
use App\Repository\PositionAccessRuleRepository;
use App\Repository\PositionRepository;
use App\Repository\UserRepository;
use App\Service\CvSearchIndexer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RecruiterRoleAuditTest extends WebTestCase
{
    private const RECRUITER_A = 'recruiter-audit-a@example.com';
    private const RECRUITER_B = 'recruiter-audit-b@example.com';
    private const CANDIDATE = 'candidate-audit@example.com';

    public function testRecruitersSharePositionsAttributesCvsDiscussionsAndLikes(): void
    {
        $clientA = static::createClient();
        $clientB = $clientA;
        $recruiterA = $this->getOrCreateUser($clientA, self::RECRUITER_A, ['ROLE_RECRUITER']);
        $recruiterB = $this->getOrCreateUser($clientB, self::RECRUITER_B, ['ROLE_RECRUITER']);
        $candidate = $this->getOrCreateUser($clientA, self::CANDIDATE, ['ROLE_CANDIDATE']);

        // 1, 2, 3, 4: Recruiter A creates; Recruiter B edits, configures, duplicates, and deletes.
        $clientA->loginUser($recruiterA);
        $attribute = $this->createAttribute($clientA, 'Audit Skill Attribute');
        $deleteAttribute = $this->createAttribute($clientA, 'Audit Delete Attribute');
        $position = $this->createPosition($clientA, $attribute);
        $positionId = $position->getId();

        $clientB->loginUser($recruiterB);
        $clientB->request('GET', '/positions/'.$positionId.'/edit');
        self::assertResponseIsSuccessful();
        $this->submitPosition($clientB, $position, 'Edited by Recruiter B', $attribute, 'Advanced');
        self::assertResponseRedirects('/positions');

        $em = $this->entityManager($clientB);
        $em->clear();
        $position = $em->getRepository(Position::class)->find($positionId);
        self::assertInstanceOf(Position::class, $position);
        self::assertSame('Edited by Recruiter B', $position->getTitle());
        self::assertCount(1, $em->getRepository(PositionAccessRule::class)->findBy(['position' => $position]));

        $this->submitPosition($clientB, $position, 'Rule Removed by B', $attribute, null);
        self::assertResponseRedirects('/positions');
        $em->clear();
        $position = $em->getRepository(Position::class)->find($positionId);
        self::assertCount(0, $em->getRepository(PositionAccessRule::class)->findBy(['position' => $position]));

        $positionsCrawler = $clientB->request('GET', '/positions');
        $positionCheckbox = $positionsCrawler->filter('[data-position-id="'.$positionId.'"]');
        $clientB->request('POST', '/positions/'.$positionId.'/duplicate', [
            '_token' => $positionCheckbox->attr('data-position-token'),
        ]);
        self::assertResponseRedirects();
        $copy = $em->getRepository(Position::class)->findOneBy(['title' => 'Rule Removed by B (copy)']);
        self::assertInstanceOf(Position::class, $copy);

        $positionsCrawler = $clientB->request('GET', '/positions');
        $positionCheckbox = $positionsCrawler->filter('[data-position-id="'.$positionId.'"]');
        $clientB->request('POST', '/positions/'.$positionId.'/delete', [
            '_token' => $positionCheckbox->attr('data-position-delete-token'),
        ]);
        self::assertResponseRedirects('/positions');
        $em->clear();
        self::assertNull($em->getRepository(Position::class)->find($positionId));

        // 6: the shared Attribute Library has no creator ownership boundary.
        $attributeId = $attribute->getId();
        $clientB->request('GET', '/attributes/'.$attributeId.'/edit');
        self::assertResponseIsSuccessful();
        $crawler = $clientB->getCrawler();
        $form = $crawler->selectButton('Save attribute')->form();
        $form['attribute[name]'] = 'Edited Shared Attribute';
        $clientB->submit($form);
        self::assertResponseRedirects('/attributes');
        $em->clear();
        self::assertSame('Edited Shared Attribute', $em->getRepository(Attribute::class)->find($attributeId)->getName());
        $attributesCrawler = $clientB->request('GET', '/attributes');
        $attributeCheckbox = $attributesCrawler->filter('[data-attribute-id="'.$deleteAttribute->getId().'"]');
        $clientB->request('POST', '/attributes/'.$deleteAttribute->getId().'/delete', [
            '_token' => $attributesCrawler->filter('[data-attribute-token]')->attr('value') ?: $attributeCheckbox->attr('data-attribute-delete-token'),
        ]);
        self::assertResponseRedirects('/attributes');
        self::assertNull($em->getRepository(Attribute::class)->find($deleteAttribute->getId()));

        // Prepare one published and one draft CV for the remaining copy.
        $em->clear();
        $copy = $em->getRepository(Position::class)->findOneBy(['title' => 'Rule Removed by B (copy)']);
        $candidate = $em->getRepository(User::class)->findOneBy(['email' => self::CANDIDATE]);
        $attribute = $em->getRepository(Attribute::class)->findOneBy(['name' => 'Edited Shared Attribute']);
        $profileValue = new ProfileAttributeValue($candidate, $attribute);
        $profileValue->setValue('Published value');
        $em->persist($profileValue);
        $publishedCv = new CV($candidate, $copy);
        $publishedCv->publish();
        $em->persist($publishedCv);
        $em->flush();
        $clientA->getContainer()->get(CvSearchIndexer::class)->refresh($publishedCv);
        $em->clear();
        $publishedCv = $em->getRepository(CV::class)->findOneBy(['status' => 'published']);
        self::assertInstanceOf(CV::class, $publishedCv);

        // 7: all three recruiter CV paths expose published CVs, without edit controls.
        $clientB->request('GET', '/search?q=Published+value&type=cvs');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Published CVs');
        self::assertSelectorTextContains('body', 'Rule Removed by B (copy)');
        $clientB->request('GET', '/positions/'.$copy->getId().'/cvs');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', $candidate->getEmail());
        self::assertSelectorTextNotContains('body', 'Draft');
        $clientB->request('GET', '/profiles/'.$candidate->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Rule Removed by B (copy)');
        self::assertSelectorNotExists('[data-cv-field]');
        self::assertSelectorNotExists('form[action*="/publish"]');
        self::assertSelectorNotExists('form[action*="/delete"]');
        $clientB->request('GET', '/cvs/'.$publishedCv->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-cv-field]');
        self::assertSelectorNotExists('form[action*="/publish"]');
        self::assertSelectorNotExists('form[action*="/delete"]');
        $clientB->request('PATCH', '/cvs/'.$publishedCv->getId().'/attributes/'.$attribute->getId(), [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['value' => 'blocked', 'version' => 1]));
        self::assertResponseStatusCodeSame(403);
        $clientB->request('POST', '/cvs/'.$publishedCv->getId().'/delete');
        self::assertResponseStatusCodeSame(403);

        // 8: any authenticated user, including Recruiter B, can post.
        $discussionCrawler = $clientB->request('GET', '/positions/'.$copy->getId().'/discussion');
        $clientB->request('POST', '/positions/'.$copy->getId().'/discussion', [
            '_token' => $discussionCrawler->filter('form input[name="_token"]')->attr('value'),
            'content' => 'Recruiter B discussion post',
        ]);
        self::assertResponseRedirects('/positions/'.$copy->getId().'/discussion');

        // 9: Recruiter B can like; Candidate cannot.
        $cvCrawler = $clientB->request('GET', '/cvs/'.$publishedCv->getId());
        $clientB->request('POST', '/cvs/'.$publishedCv->getId().'/like', [
            '_token' => $cvCrawler->filter('form[action*="/like"] input[name="_token"]')->attr('value'),
        ]);
        self::assertResponseRedirects('/cvs/'.$publishedCv->getId());
        $em->clear();
        self::assertNotNull($em->getRepository(CvLike::class)->findOneBy(['cv' => $publishedCv, 'recruiter' => $recruiterB]));
        $clientA->loginUser($candidate);
        $clientA->request('POST', '/cvs/'.$publishedCv->getId().'/like');
        self::assertResponseStatusCodeSame(403);
    }

    private function createPosition(KernelBrowser $client, Attribute $attribute): Position
    {
        $crawler = $client->request('GET', '/positions/new');
        $client->request('POST', '/positions/new', [
            '_token' => $crawler->filter('form input[name="_token"]')->attr('value'),
            'title' => 'Recruiter A Position',
            'short_description' => 'Shared recruiter audit position',
            'is_public' => '1',
            'attributes' => [$attribute->getId()],
            'rules' => [],
            'max_projects' => '3',
        ]);
        self::assertResponseRedirects('/positions');
        $em = $this->entityManager($client);
        $position = $em->getRepository(Position::class)->findOneBy(['title' => 'Recruiter A Position']);
        self::assertInstanceOf(Position::class, $position);

        return $position;
    }

    private function submitPosition(KernelBrowser $client, Position $position, string $title, Attribute $attribute, ?string $ruleValue): void
    {
        $crawler = $client->request('GET', '/positions/'.$position->getId().'/edit');
        $client->request('POST', '/positions/'.$position->getId().'/edit', [
            '_token' => $crawler->filter('form input[name="_token"]')->attr('value'),
            'version' => $position->getVersion(),
            'title' => $title,
            'short_description' => 'Updated by Recruiter B',
            'is_public' => '1',
            'attributes' => [$attribute->getId()],
            'rules' => $ruleValue === null ? [] : [$attribute->getId() => ['operator' => AccessRuleOperator::Equals->value, 'value' => $ruleValue]],
            'max_projects' => '3',
        ]);
    }

    private function createAttribute(KernelBrowser $client, string $name): Attribute
    {
        $client->request('GET', '/attributes/new');
        $client->submitForm('Save attribute', [
            'attribute[name]' => $name,
            'attribute[category]' => '4',
            'attribute[description]' => 'Recruiter audit attribute',
            'attribute[type]' => '0',
            'attribute[optionsText]' => '',
        ]);
        self::assertResponseRedirects('/attributes');
        $attribute = $this->entityManager($client)->getRepository(Attribute::class)->findOneBy(['name' => $name]);
        self::assertInstanceOf(Attribute::class, $attribute);

        return $attribute;
    }

    private function getOrCreateUser(KernelBrowser $client, string $email, array $roles): User
    {
        $em = $this->entityManager($client);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            $user = new User();
            $user->setEmail($email)->setPassword('test')->setRoles($roles);
            $em->persist($user);
        } else {
            $user->setRoles($roles);
        }
        $em->flush();

        return $user;
    }

    private function entityManager(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get('doctrine')->getManager();
    }

    public static function tearDownAfterClass(): void
    {
        $kernel = self::bootKernel();
        $em = $kernel->getContainer()->get('doctrine')->getManager();
        $userRepository = $em->getRepository(User::class);
        foreach ([self::RECRUITER_A, self::RECRUITER_B, self::CANDIDATE] as $email) {
            $user = $userRepository->findOneBy(['email' => $email]);
            if ($user instanceof User) {
                $em->remove($user);
            }
        }
        foreach ($em->getRepository(Position::class)->findBy(['title' => ['Recruiter A Position', 'Edited by Recruiter B', 'Rule Removed by B (copy)']]) as $position) {
            $em->remove($position);
        }
        foreach ($em->getRepository(Attribute::class)->findBy(['name' => ['Audit Skill Attribute', 'Edited Shared Attribute', 'Audit Delete Attribute']]) as $attribute) {
            $em->remove($attribute);
        }
        $em->flush();
        parent::tearDownAfterClass();
    }
}