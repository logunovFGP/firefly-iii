<?php

declare(strict_types=1);

/*
 * ProcessesNewUserRegistration.php
 * Copyright (c) 2026 james@firefly-iii.org
 *
 * This file is part of Firefly III (https://github.com/firefly-iii).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace FireflyIII\Listeners\Security\System;

use Database\Seeders\ExchangeRateSeeder;
use FireflyIII\Enums\UserRoleEnum;
use FireflyIII\Events\Security\System\NewUserRegistered;
use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Models\GroupMembership;
use FireflyIII\Models\UserGroup;
use FireflyIII\Models\UserRole;
use FireflyIII\Notifications\Admin\UserRegistration as AdminRegistrationNotification;
use FireflyIII\Notifications\Notifiables\OwnerNotifiable;
use FireflyIII\Notifications\NotificationSender;
use FireflyIII\Notifications\User\UserRegistration as UserRegistrationNotification;
use FireflyIII\Repositories\User\UserRepositoryInterface;
use FireflyIII\Support\Facades\FireflyConfig;
use FireflyIII\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class HandlesNewUserRegistration implements ShouldQueue
{
    public function handle(NewUserRegistered $event): void
    {
        $this->sendRegistrationMail($event->user);
        $this->sendAdminRegistrationNotification($event->user, $event->owner);
        $this->attachUserRole($event->user);
        $this->createGroupMembership($event->user);
        $this->createExchangeRates();
    }

    private function attachUserRole(User $user): void
    {
        /** @var UserRepositoryInterface $repository */
        $repository = app(UserRepositoryInterface::class);

        // first user ever?
        if (1 === $repository->count()) {
            Log::debug('User count is one, attach role.');
            $repository->attachRole($user, 'owner');
        }
    }

    /**
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    private function createExchangeRates(): void
    {
        $seeder = new ExchangeRateSeeder();
        $seeder->run();
    }

    /**
     * @throws FireflyException
     */
    private function createGroupMembership(User $user): void
    {
        $groupCounter        = 0;

        /** @var null|UserGroup $group */
        $group               = null;
        $groupTitle          = '';

        // create a new group.
        while (null === $group) { // @phpstan-ignore-line
            if ($groupCounter > 99) {
                throw new FireflyException('Email address can no longer be used for registrations.');
            }
            $groupTitle = 0 === $groupCounter ? $user->email : sprintf('%s-%d', $user->email, $groupCounter);
            ++$groupCounter;

            try {
                $group = UserGroup::firstOrCreate(['title' => $groupTitle], ['title' => $groupTitle]);
            } catch (QueryException $exception) {
                if (!$this->isDuplicateGroupTitle($exception)) {
                    throw $exception;
                }
                $group = UserGroup::where('title', $groupTitle)->first();
            }
        }

        /** @var null|UserRole $role */
        $role                = UserRole::where('title', UserRoleEnum::OWNER->value)->first();
        if (null === $role) {
            throw new FireflyException('The user role is unexpectedly empty. Did you run all migrations?');
        }
        GroupMembership::create(['user_id'       => $user->id, 'user_group_id' => $group->id, 'user_role_id'  => $role->id]);
        $user->user_group_id = $group->id;
        $user->save();
    }

    private function isDuplicateGroupTitle(QueryException $exception): bool
    {
        $errorCode = (int) ($exception->errorInfo[1] ?? 0);
        if (1062 !== $errorCode) {
            return false;
        }

        return false !== str_contains($exception->getMessage(), 'user_groups_title_unique');
    }

    private function sendAdminRegistrationNotification(User $user, OwnerNotifiable $owner): void
    {
        $sendMail = (bool) FireflyConfig::get('notification_admin_new_reg', true)->data;
        if (!$sendMail) {
            return;
        }
        NotificationSender::send($owner, new AdminRegistrationNotification($user));
    }

    private function sendRegistrationMail(User $user): void
    {
        $sendMail = (bool) FireflyConfig::get('notification_user_new_reg', true)->data;
        if (!$sendMail) {
            return;
        }

        NotificationSender::send($user, new UserRegistrationNotification());
    }
}
