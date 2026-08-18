import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { ZXingScannerModule } from '@zxing/ngx-scanner';

@Component({
  selector: 'app-scanner',
  standalone: true,
  imports: [CommonModule, ZXingScannerModule],
  templateUrl: './scanner.html',
  styleUrl: './scanner.css'
})
export class Scanner {
  private auth = inject(AuthService);
  private router = inject(Router);

  hasDevices = false;
  hasPermission: boolean | null = null;
  
  onCamerasFound(devices: MediaDeviceInfo[]): void {
    this.hasDevices = Boolean(devices && devices.length > 0);
  }

  onHasPermission(has: boolean): void {
    this.hasPermission = has;
  }

  handleQrCodeResult(resultString: string): void {
    const urlParts = resultString.split('/verification/');
    
    if (urlParts.length > 1) {
      const token = urlParts[1];
      this.router.navigate(['/verification', token]);
    } else {
      alert("Ce QR Code n'est pas reconnu par le système.");
    }
  }

  logout(): void {
    this.auth.logout();
    this.router.navigate(['/login']);
  }
}