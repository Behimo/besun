<script setup>
const { isInTenantShell, hasCoreModule } = useAppShell()
const router = useRouter()

const openChat = () => {
  router.push({ name: 'apps-crm-team-chat' })
}
</script>

<template>
  <IconBtn
    v-if="isInTenantShell"
    class="me-1"
    :disabled="!hasCoreModule"
    @click="openChat"
  >
    <VBadge
      dot
      color="success"
      location="top end"
      offset-x="3"
      offset-y="3"
    >
      <VIcon
        icon="tabler-messages"
        size="26"
      />
    </VBadge>
    <VTooltip
      activator="parent"
      location="bottom"
    >
      گفتگوی تیم
    </VTooltip>
  </IconBtn>
</template>
