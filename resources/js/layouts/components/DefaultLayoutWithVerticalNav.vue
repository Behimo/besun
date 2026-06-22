<script setup>
import ownerNav from '@/navigation/vertical/owner-nav'
import platformAdminNav from '@/navigation/vertical/platform-admin-nav'
import platformSupportNav from '@/navigation/vertical/platform-support-nav'
import { buildTenantNav } from '@/navigation/vertical/tenant-nav'
import WorkspaceSwitcher from '@/layouts/components/WorkspaceSwitcher.vue'
import { syncManagerBiAbility } from '@/utils/biAbility'

const { userData, isInTenantShell, isInPlatformShell, isPlatformAdmin } = useAppShell()
const ability = useAbility()

watch(userData, data => {
  syncManagerBiAbility(ability)
}, { immediate: true, deep: true })

const navItems = computed(() => {
  if (isInTenantShell.value)
    return buildTenantNav(userData.value)

  if (isInPlatformShell.value)
    return isPlatformAdmin.value ? platformAdminNav : platformSupportNav

  return ownerNav
})

const shellNavKey = computed(() => {
  if (isInTenantShell.value)
    return `tenant-${userData.value?.tenant?.id}`

  if (isInPlatformShell.value)
    return isPlatformAdmin.value ? 'platform-admin' : 'platform-support'

  return 'account'
})

// Components
import Footer from '@/layouts/components/Footer.vue'
import NavbarTeamChat from '@/layouts/components/NavbarTeamChat.vue'
import NavbarThemeSwitcher from '@/layouts/components/NavbarThemeSwitcher.vue'
import NavBarNotifications from '@/layouts/components/NavBarNotifications.vue'
import UserProfile from '@/layouts/components/UserProfile.vue'

// @layouts plugin
import { VerticalNavLayout } from '@layouts'
</script>

<template>
  <VerticalNavLayout
    :key="shellNavKey"
    :nav-items="navItems"
  >
    <!-- 👉 navbar -->
    <template #navbar="{ toggleVerticalOverlayNavActive }">
      <div class="d-flex h-100 align-center">
        <IconBtn
          id="vertical-nav-toggle-btn"
          class="ms-n3 d-lg-none"
          @click="toggleVerticalOverlayNavActive(true)"
        >
          <VIcon
            size="26"
            icon="tabler-menu-2"
          />
        </IconBtn>

        <WorkspaceSwitcher
          v-if="isInTenantShell"
          class="ms-lg-2"
        />

        <VChip
          v-else-if="isInPlatformShell"
          size="small"
          color="error"
          variant="tonal"
          class="ms-lg-2"
          prepend-icon="tabler-shield-lock"
        >
          {{ isPlatformAdmin ? 'مدیریت پلتفرم' : 'پشتیبانی پلتفرم' }}
        </VChip>

        <VChip
          v-else-if="userData"
          size="small"
          color="primary"
          variant="tonal"
          class="ms-lg-2"
          prepend-icon="tabler-user"
        >
          حساب شخصی
        </VChip>

        <VSpacer />
        <NavBarNotifications />
        <NavbarTeamChat />
        <NavbarThemeSwitcher />
        <UserProfile />
      </div>
    </template>

    <!-- 👉 Pages -->
    <slot />

    <!-- 👉 Footer -->
    <template #footer>
      <Footer />
    </template>
  </VerticalNavLayout>
</template>
