<?php

namespace App\Tests\Security;

use App\Entity\Attribute;
use App\Entity\CV;
use App\Entity\CvLike;
use App\Entity\Position;
use App\Entity\PositionAttribute;
use App\Entity\ProfileAttributeValue;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\AttributeCategory;
use App\Enum\AttributeType;
use App\Enum\CvStatus;
use App\Repository\AttributeRepository;
use App\Repository\CvRepository;
use App\Repository\UserRepository;
use App\Service\CvSearchIndexer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdministratorRoleAuditTest extends WebTestCase
{
    private const ADMIN = 'admin-audit@example.com';
    private const CANDIDATE = 'candidate-owner-audit@example.com';
    private const RECRUITER = 'recruiter-owner-audit@example.com';
    private const TARGET = 'target-user-audit@example.com';

    public function testAdminCanActAsCandidateAndRecruiterOwners(): void
    {
        $client = static::createClient();
        $admin = $this->getOrCreateUser($client, self::ADMIN, ['ROLE_ADMIN']);
        $candidate = $this->getOrCreateUser($client, self::CANDIDATE, ['ROLE_CANDIDATE']);
        $recruiter = $this->getOrCreateUser($client, self::RECRUITER, ['ROLE_RECRUITER']);
        $target = $this->getOrCreateUser($client, self::TARGET, ['ROLE_CANDIDATE']);

        $em = $this->entityManager($client);
        $attribute = $this->createAttribute($em, 'Administrator Audit Attribute');
        $position = (new Position())->setTitle('Administrator Audit Position')->setShortDescription('Owner-facing admin audit')->setIsPublic(true);
        $em->persist($position);
        $em->persist(new PositionAttribute($position, $attribute));
        $profileValue = (new ProfileAttributeValue($candidate, $attribute))->setValue('Original admin value');
        $em->persist($profileValue);
        $project = (new Project($candidate))->setName('Candidate-owned project')->setDescription('Admin test project')->setTags(['php']);
        $em->persist($project);
        $draft = new CV($candidate, $position);
        $published = new CV($candidate, (new Position())->setTitle('Administrator Published Position')->setShortDescription('Published CV path')->setIsPublic(true));
        $published->publish();
        $em->persist($draft);
        $em->persist($published->getPosition());
        $em->persist(new PositionAttribute($published->getPosition(), $attribute));
        $em->persist($published);
        $em->flush();
        $client->getContainer()->get(CvSearchIndexer::class)->refresh($published);
        $em->clear();

        $candidate = $em->getRepository(User::class)->findOneBy(['email' => self::CANDIDATE]);
        $position = $em->getRepository(Position::class)->findOneBy(['title' => 'Administrator Audit Position']);
        $draft = $em->getRepository(CV::class)->findOneBy(['candidate' => $candidate, 'position' => $position]);
        $published = $em->getRepository(CV::class)->findOneBy(['candidate' => $candidate, 'status' => CvStatus::Published]);
        self::assertInstanceOf(User::class, $candidate);
        self::assertInstanceOf(Position::class, $position);
        self::assertInstanceOf(CV::class, $draft);
        self::assertInstanceOf(CV::class, $published);

        $client->loginUser($admin);
        $authorizationChecker = $client->getContainer()->get('security.authorization_checker');
        self::assertTrue($authorizationChecker->isGranted('ROLE_RECRUITER'));
        self::assertTrue($authorizationChecker->isGranted('ROLE_CANDIDATE'));

        // 1, 2: Admin sees the candidate owner screen and persists a profile edit.
        $profileCrawler = $client->request('GET', '/profile?user='.$candidate->getId().'&tab=info');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-autosave-value]');
        self::assertSelectorTextContains('body', 'Me');
        self::assertSelectorTextContains('body', 'Info');
        self::assertSelectorTextContains('body', 'Projects');
        self::assertSelectorTextContains('body', 'CVs');
        $valueId = $em->getRepository(ProfileAttributeValue::class)->findOneBy(['user' => $candidate, 'attribute' => $attribute])->getId();
        $client->request('PATCH', '/profile/attributes/'.$valueId.'/autosave?user='.$candidate->getId(), [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['value' => 'Fixed by administrator', 'version' => 1]));
        self::assertResponseIsSuccessful();
        $em->clear();
        self::assertSame('Fixed by administrator', $em->getRepository(ProfileAttributeValue::class)->find($valueId)->getValue());

        // 3: Admin receives the owner CV UI for both draft and published CVs and can write back.
        $client->request('GET', '/cvs/'.$draft->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-cv-field]');
        $client->request('PATCH', '/cvs/'.$draft->getId().'/attributes/'.$attribute->getId(), [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['value' => 'Edited through CV', 'version' => 2]));
        self::assertResponseIsSuccessful();
        $em->clear();
        self::assertSame('Edited through CV', $em->getRepository(ProfileAttributeValue::class)->find($valueId)->getValue());
        $draftCrawler = $client->request('GET', '/cvs/'.$draft->getId());
        $client->request('POST', '/cvs/'.$draft->getId().'/publish', [
            '_token' => $draftCrawler->filter('form[action*="/publish"] input[name="_token"]')->attr('value'),
            'version' => $draft->getVersion(),
        ]);
        self::assertResponseRedirects('/cvs/'.$draft->getId());
        $client->request('GET', '/cvs/'.$published->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-cv-field]');
        $currentValue = $em->getRepository(ProfileAttributeValue::class)->find($valueId);
        $client->request('PATCH', '/cvs/'.$published->getId().'/attributes/'.$attribute->getId(), [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['value' => 'Edited on published CV', 'version' => $currentValue->getVersion()]));
        self::assertResponseIsSuccessful();

        // 4, 5: Admin can edit positions and perform Recruiter actions through the same routes.
        $positionCrawler = $client->request('GET', '/positions/'.$position->getId().'/edit');
        self::assertResponseIsSuccessful();
        $client->request('POST', '/positions/'.$position->getId().'/edit', [
            '_token' => $positionCrawler->filter('form input[name="_token"]')->attr('value'),
            'version' => $position->getVersion(),
            'title' => 'Admin-edited Position',
            'short_description' => 'Edited by Admin',
            'is_public' => '1',
            'attributes' => [$attribute->getId()],
            'rules' => [$attribute->getId() => ['operator' => 'equals', 'value' => 'Admin']],
            'max_projects' => '3',
        ]);
        self::assertResponseRedirects('/positions');
        $client->request('GET', '/attributes');
        self::assertResponseIsSuccessful();
        $attributeCrawler = $client->request('GET', '/attributes/'.$attribute->getId().'/edit');
        $attributeForm = $attributeCrawler->selectButton('Save attribute')->form();
        $attributeForm['attribute[name]'] = 'Administrator Edited Attribute';
        $client->submit($attributeForm);
        self::assertResponseRedirects('/attributes');
        $deleteAttribute = $this->createAttribute($em, 'Administrator Delete Attribute');
        $attributeId = $deleteAttribute->getId();
        $attributeList = $client->request('GET', '/attributes');
        $attributeCheckbox = $attributeList->filter('[data-attribute-id="'.$attributeId.'"]');
        $client->request('POST', '/attributes/'.$attributeId.'/delete', [
            '_token' => $attributeCheckbox->attr('data-attribute-delete-token'),
        ]);
        self::assertResponseRedirects('/attributes');
        $em->clear();
        self::assertNull($em->getRepository(Attribute::class)->find($attributeId));
        $newAttribute = $this->createAttribute($em, 'Administrator Position Action Attribute');
        $newPositionCrawler = $client->request('GET', '/positions/new');
        $client->request('POST', '/positions/new', [
            '_token' => $newPositionCrawler->filter('form input[name="_token"]')->attr('value'),
            'title' => 'Administrator Action Position',
            'short_description' => 'Created by Admin',
            'is_public' => '1',
            'attributes' => [$newAttribute->getId()],
            'rules' => [],
            'max_projects' => '3',
        ]);
        self::assertResponseRedirects('/positions');
        $actionPosition = $em->getRepository(Position::class)->findOneBy(['title' => 'Administrator Action Position']);
        $positionList = $client->request('GET', '/positions');
        $positionCheckbox = $positionList->filter('[data-position-id="'.$actionPosition->getId().'"]');
        $client->request('POST', '/positions/'.$actionPosition->getId().'/duplicate', ['_token' => $positionCheckbox->attr('data-position-token')]);
        self::assertResponseRedirects();
        $copy = $em->getRepository(Position::class)->findOneBy(['title' => 'Administrator Action Position (copy)']);
        self::assertInstanceOf(Position::class, $copy);
        $positionList = $client->request('GET', '/positions');
        $positionCheckbox = $positionList->filter('[data-position-id="' . $actionPosition->getId() . '"]');
        $client->request('POST', '/positions/'.$actionPosition->getId().'/delete', ['_token' => $positionCheckbox->attr('data-position-delete-token')]);
        self::assertResponseRedirects('/positions');
        $client->request('GET', '/positions/'.$position->getId().'/cvs');
        self::assertResponseIsSuccessful();
        $client->request('GET', '/profiles/'.$candidate->getId());
        self::assertResponseIsSuccessful();
        $client->request('GET', '/profile?user='.$recruiter->getId().'&tab=me');
        self::assertResponseIsSuccessful();
        $client->request('GET', '/search?q=Edited+through+CV&type=cvs');
        self::assertResponseIsSuccessful();
        $discussionCrawler = $client->request('GET', '/positions/'.$position->getId().'/discussion');
        self::assertResponseIsSuccessful();
        $client->request('POST', '/positions/'.$position->getId().'/discussion', [
            '_token' => $discussionCrawler->filter('form input[name="_token"]')->attr('value'),
            'content' => 'Administrator discussion post',
        ]);
        self::assertResponseRedirects('/positions/'.$position->getId().'/discussion');
        $cvCrawler = $client->request('GET', '/cvs/'.$published->getId());
        $client->request('POST', '/cvs/'.$published->getId().'/like', [
            '_token' => $cvCrawler->filter('form[action*="/like"] input[name="_token"]')->attr('value'),
        ]);
        self::assertResponseRedirects('/cvs/'.$published->getId());

        // 6: Admin can create a candidate-owned CV and project through candidate routes.
        $profileCvsCrawler = $client->request('GET', '/profile?user='.$candidate->getId().'&tab=cvs');
        self::assertResponseIsSuccessful();
        $client->request('POST', '/cvs/positions/'.$position->getId().'/new?user='.$candidate->getId(), [
            '_token' => $profileCvsCrawler->filter('[data-cv-token]')->attr('value'),
        ]);
        self::assertResponseRedirects();
        $projectCrawler = $client->request('GET', '/profile/projects/new?user='.$candidate->getId());
        $client->request('POST', '/profile/projects/new?user='.$candidate->getId(), [
            '_token' => $projectCrawler->filter('form input[name="_token"]')->attr('value'),
            'name' => 'Admin-created candidate project',
            'description' => 'Created by Admin',
            'tags' => 'symfony',
        ]);
        self::assertResponseRedirects('/profile?tab=projects');

        // 7: Admin can view and mutate user management records.
        $adminCrawler = $client->request('GET', '/admin/users');
        self::assertResponseIsSuccessful();
        $target = $em->getRepository(User::class)->findOneBy(['email' => self::TARGET]);
        $targetRow = $adminCrawler->filter('[data-admin-user-id="'.$target->getId().'\"]');
        $client->request('POST', '/admin/users/'.$target->getId().'/toggle-blocked', ['_token' => $targetRow->attr('data-block-token')]);
        self::assertResponseRedirects('/admin/users');
        $em->clear();
        self::assertTrue($em->getRepository(User::class)->find($target->getId())->isBlocked());
        $client->request('GET', '/admin/users');
        $targetRow = $client->getCrawler()->filter('[data-admin-user-id="'.$target->getId().'\"]');
        $client->request('POST', '/admin/users/'.$target->getId().'/toggle-blocked', ['_token' => $targetRow->attr('data-block-token')]);
        self::assertResponseRedirects('/admin/users');
        $client->request('GET', '/admin/users');
        $targetRow = $client->getCrawler()->filter('[data-admin-user-id="'.$target->getId().'\"]');
        $client->request('POST', '/admin/users/'.$target->getId().'/roles', ['_token' => $targetRow->attr('data-role-token'), 'role' => 'ROLE_RECRUITER', 'role_action' => 'add']);
        self::assertResponseRedirects('/admin/users');
        $client->request('GET', '/admin/users');
        $targetRow = $client->getCrawler()->filter('[data-admin-user-id="'.$target->getId().'\"]');
        $client->request('POST', '/admin/users/'.$target->getId().'/roles', ['_token' => $targetRow->attr('data-role-token'), 'role' => 'ROLE_RECRUITER', 'role_action' => 'remove']);
        self::assertResponseRedirects('/admin/users');
        $client->request('GET', '/admin/users');
        $targetRow = $client->getCrawler()->filter('[data-admin-user-id="'.$target->getId().'\"]');
        $client->request('POST', '/admin/users/'.$target->getId().'/delete', ['_token' => $targetRow->attr('data-delete-token')]);
        self::assertResponseRedirects('/admin/users');
        self::assertNull($em->getRepository(User::class)->find($target->getId()));
    }

    private function createAttribute(EntityManagerInterface $em, string $name): Attribute
    {
        $attribute = (new Attribute())->setName($name)->setCategory(AttributeCategory::Skills)->setType(AttributeType::String);
        $em->persist($attribute);
        $em->flush();

        return $attribute;
    }

    private function getOrCreateUser(KernelBrowser $client, string $email, array $roles): User
    {
        $em = $this->entityManager($client);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]) ?? new User();
        $user->setEmail($email)->setPassword('test')->setRoles($roles)->setBlocked(false);
        $em->persist($user);
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
        foreach ([self::ADMIN, self::CANDIDATE, self::RECRUITER, self::TARGET] as $email) {
            $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($user instanceof User) {
                $em->remove($user);
            }
        }
        foreach ($em->getRepository(Attribute::class)->findBy(['name' => ['Administrator Audit Attribute', 'Administrator Edited Attribute', 'Administrator Delete Attribute', 'Administrator Position Action Attribute']]) as $attribute) {
            $em->remove($attribute);
        }
        foreach ($em->getRepository(Position::class)->findBy(['title' => ['Administrator Audit Position', 'Admin-edited Position', 'Administrator Published Position', 'Administrator Action Position', 'Administrator Action Position (copy)']]) as $position) {
            $em->remove($position);
        }
        $em->flush();
        parent::tearDownAfterClass();
    }
}