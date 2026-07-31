export type RequestType = 'Première adhésion' | 'Renouvellement';
export type MemberStatus = 'EN_ATTENTE' | 'ACTIVE' | 'SUSPENDUE' | 'EXPIREE' | 'RESILIEE';

export interface MembershipHistory {
  date: string;
  label: string;
  amount: number;
  status: MemberStatus;
}

export interface MemberApplication {
  id: string;
  memberNumber: string;
  qrToken: string;
  status: MemberStatus;
  createdAt: string;
  firstName: string;
  lastName: string;
  birthDate: string;
  birthPlace: string;
  postalAddress: string;
  phone: string;
  personalEmail: string;
  professionalStatus: string;
  employers: string;
  functionTitle: string;
  pressCardNumber: string;
  pressCardExpiry: string;
  professionalEmail?: string;
  professionalPhone?: string;
  requestType: RequestType;
  currentMemberNumber?: string;
  pressCardFile?: string;
  cvFile?: string;
  photoFile?: string;
  photoDataUrl?: string;
  declarationAccepted: boolean;
  signatureName: string;
  signatureDate: string;
  contributionAmount: number;
  paymentMethod: string;
  directoryConsent: boolean;
  privacyAccepted: boolean;
  login: string;
  password: string;
  paymentPhone?: string;
  transactionId?: string;
}
