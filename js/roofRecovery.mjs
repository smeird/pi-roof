// Presentation only: firmware owns recovery, retries and motor authorization.
export function describeCloseRecovery({ closePending, closeResult, closeRetryCount, closeLimit, fault, faultCode } = {}) {
  const pending = closePending === '1';
  const permanentFault = (fault === '1' && faultCode !== 'BOTH_LIMITS') ||
    (faultCode && faultCode !== '0' && faultCode !== 'BOTH_LIMITS');
  const recovering = pending && !permanentFault && (closeResult === 'recovering' || faultCode === 'BOTH_LIMITS');
  const retries = /^\d+$/.test(String(closeRetryCount ?? '')) ? Number(closeRetryCount) : null;
  // Missing recovery topics retain compatibility with older controller firmware.
  const confirmed = closeLimit === '1' && !pending &&
    (!closeResult || closeResult === 'idle' || closeResult === 'closed');
  const label = recovering ? 'Recovering — close pending' :
    closeLimit === '1' ? 'Confirming closed limit' : 'Close pending';
  return { pending, recovering, retries, confirmed, label };
}
