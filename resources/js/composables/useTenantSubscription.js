export const SUBSCRIPTION_PERIODS = [
  { title: 'ماهانه', value: 'monthly' },
  { title: '۶ ماهه', value: 'semi_annual' },
  { title: 'سالانه', value: 'annual' },
]

export const formatRial = value => {
  if (value == null || Number.isNaN(Number(value)))
    return '—'

  return `${Number(value).toLocaleString('fa-IR')} ریال`
}

export const periodLabel = period =>
  SUBSCRIPTION_PERIODS.find(p => p.value === period)?.title ?? period

export const seatPriceForPeriod = (module, period) => {
  if (! module)
    return 0

  if (period === 'semi_annual')
    return module.seat_semi_annual_price ?? module.semi_annual_price ?? 0

  if (period === 'annual')
    return module.seat_annual_price ?? module.annual_price ?? 0

  return module.seat_monthly_price ?? module.monthly_price ?? 0
}

/** قیمت prorate افزونه بر اساس روزهای باقیمانده اشتراک پایه */
export const proratedAddonPrice = (monthlyPrice, remainingDays) => {
  const monthly = Number(monthlyPrice) || 0
  const days = Math.max(1, Number(remainingDays) || 30)

  return Math.round((monthly / 30) * days)
}

export const addonPriceForTenant = (module, tenant) => {
  if (! tenant?.has_core_module)
    return module.monthly_price

  return proratedAddonPrice(module.monthly_price, tenant.core_remaining_days)
}

export const previewTenantSubscription = async (tenantId, body) => {
  return $api(`/tenants/${tenantId}/subscription/preview`, {
    method: 'POST',
    body,
  })
}

export const purchaseTenantSubscription = async (tenantId, body) => {
  return $api(`/tenants/${tenantId}/subscription/purchase`, {
    method: 'POST',
    body,
  })
}
