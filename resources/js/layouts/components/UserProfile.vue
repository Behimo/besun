<script setup>
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { isPlatformStaffSession, platformLoginRouteForPortal } from '@/composables/usePlatformAdmin'

const router = useRouter()
const ability = useAbility()
const { isInTenantShell, exitTenantShell } = useAppShell()

const userData = useCookie('userData')

const logout = async () => {
  const isPlatform = isPlatformStaffSession(userData.value)
  const portal = userData.value?.isPlatformSupport ? 'support' : 'admin'

  try {
    await $api(isPlatform ? '/auth/platform/logout' : '/auth/logout', { method: 'POST' })
  } catch (e) {
    console.error(e)
  }

  clearAuthSession(ability)

  if (isPlatform) {
    await router.replace({ ...platformLoginRouteForPortal(portal), query: { logout: '1' } })

    return
  }

  await router.replace({ name: 'login', query: { logout: '1' } })
}

const goToProfile = async () => {
  if (isInTenantShell.value)
    await exitTenantShell()

  await router.push({ name: 'apps-profile' })
}

const userProfileList = computed(() => {
  if (isPlatformStaffSession(userData.value)) {
    return []
  }

  const items = [
    {
      type: 'navItem',
      icon: 'tabler-user',
      title: 'پروفایل',
      action: 'goProfile',
    },
  ]

  if (isInTenantShell.value) {
    items.push({
      type: 'navItem',
      icon: 'tabler-home',
      title: 'بازگشت به حساب کاربری',
      action: 'exitTenant',
    })
  }

  return items
})

const onMenuClick = async item => {
  if (item.action === 'exitTenant')
    await exitTenantShell()
  else if (item.action === 'goProfile')
    await goToProfile()
}
</script>

<template>
  <VBadge
    v-if="userData"
    dot
    bordered
    location="bottom right"
    offset-x="1"
    offset-y="2"
    color="success"
  >
    <VAvatar
      size="38"
      class="cursor-pointer"
      :color="!(userData && userData.avatar) ? 'primary' : undefined"
      :variant="!(userData && userData.avatar) ? 'tonal' : undefined"
    >
      <VImg
        v-if="userData && userData.avatar"
        :src="userData.avatar"
      />
      <VIcon
        v-else
        icon="tabler-user"
      />

      <VMenu
        activator="parent"
        width="260"
        location="bottom end"
        offset="12px"
      >
        <VList>
          <VListItem>
            <div class="d-flex gap-2 align-center">
              <VAvatar
                color="primary"
                variant="tonal"
              >
                <VIcon icon="tabler-user" />
              </VAvatar>
              <div>
                <h6 class="text-h6 font-weight-medium">
                  {{ userData.fullName || userData.username }}
                </h6>
                <VListItemSubtitle class="text-disabled">
                  {{ userData.phone || userData.email }}
                </VListItemSubtitle>
              </div>
            </div>
          </VListItem>

          <VDivider class="my-2" />

          <PerfectScrollbar :options="{ wheelPropagation: false }">
            <template
              v-for="(item, idx) in userProfileList"
              :key="idx"
            >
              <VListItem
                v-if="item.to"
                :to="item.to"
              >
                <template #prepend>
                  <VIcon
                    :icon="item.icon"
                    size="22"
                  />
                </template>
                <VListItemTitle>{{ item.title }}</VListItemTitle>
              </VListItem>
              <VListItem
                v-else
                @click="onMenuClick(item)"
              >
                <template #prepend>
                  <VIcon
                    :icon="item.icon"
                    size="22"
                  />
                </template>
                <VListItemTitle>{{ item.title }}</VListItemTitle>
              </VListItem>
            </template>

            <div class="px-4 py-2">
              <VBtn
                block
                size="small"
                color="error"
                append-icon="tabler-logout"
                @click="logout"
              >
                خروج از سیستم
              </VBtn>
            </div>
          </PerfectScrollbar>
        </VList>
      </VMenu>
    </VAvatar>
  </VBadge>
</template>
