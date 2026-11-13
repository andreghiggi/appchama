export function rideStatusLabel(status: string): string {
  const map: Record<string, string> = {
    requested: 'Solicitada',
    searching: 'Buscando motorista',
    accepted: 'Aceita',
    driver_arrived: 'Motorista chegou',
    in_progress: 'Em andamento',
    awaiting_payment: 'Aguardando pagamento',
    completed: 'Concluída',
    canceled_by_passenger: 'Cancelada (passageiro)',
    canceled_by_driver: 'Cancelada (motorista)',
    no_drivers_available: 'Sem motoristas',
  };
  return map[status] ?? status;
}

export function subscriptionStatusLabel(status: string): string {
  const map: Record<string, string> = {
    active: 'Ativa',
    pending: 'Pendente',
    overdue: 'Em atraso',
    suspended: 'Suspensa',
  };
  return map[status] ?? status;
}

export function onlineLabel(online: boolean): string {
  return online ? 'Disponível' : 'Indisponível';
}
