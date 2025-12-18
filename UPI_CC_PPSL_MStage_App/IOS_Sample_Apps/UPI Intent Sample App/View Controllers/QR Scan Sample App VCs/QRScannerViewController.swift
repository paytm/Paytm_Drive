//
//  QRScannerViewController.swift
//  UPI Intent Sample App
//
//  Created by Vishrut tewatia on 27/09/24.
//

import UIKit
import AVFoundation

class QRScannerViewController: UIViewController {
    
    //MARK: IB Outlets
    
    @IBOutlet weak var qrCodeView: UIView! {
        didSet {
            qrCodeView.layer.borderWidth = 4
            qrCodeView.layer.borderColor = CGColor(red: 0, green: 0, blue: 0, alpha: 1)
        }
    }
    
    //MARK: Variables
    
    let session = AVCaptureSession()
    var sessionStarted: Bool = false
    
    //MARK: Lifecycle Methods
    
    override func viewWillAppear(_ animated: Bool) {
        super.viewWillAppear(animated)
        /// Resuming session upon coming back to the view, not re-initiating the session because we have already configured it and the session can be reused.
        if !session.isRunning && sessionStarted {
            DispatchQueue.global(qos: .background).async {
                self.session.startRunning()
            }
        }
    }
    
    override func viewDidLoad() {
        super.viewDidLoad()
        setupCameraOnPermissionCheck()
        NotificationCenter.default.addObserver(self, selector: #selector(sceneDidBecomeActive), name: NSNotification.Name("SceneDidBecomeActive"), object: nil) ///Observer to check switching between settings and main app, user can switch without doing anything so permission alert has to be shown again.
    }
    
    override func viewWillDisappear(_ animated: Bool) {
        super.viewWillDisappear(animated)
        session.stopRunning()
    }
    
    override func viewDidDisappear(_ animated: Bool) {
        super.viewDidDisappear(animated)
        NotificationCenter.default.removeObserver(self, name: NSNotification.Name("SceneDidBecomeActive"), object: nil)
    }
    
    // MARK: Internal Functions
    
    @objc func sceneDidBecomeActive() {
        if !session.isRunning {
            setupCameraOnPermissionCheck()
        }
    }
    
    ///Utilises AV Utility to know the camera permission status
    
    func requestCameraAccess(completion: @escaping (Bool) -> Void) {
        let cameraAuthorizationStatus = AVCaptureDevice.authorizationStatus(for: .video)
        
        switch cameraAuthorizationStatus {
        case .notDetermined:
            // Request permission if not determined yet
            AVCaptureDevice.requestAccess(for: .video) { granted in
                DispatchQueue.main.async {
                    completion(granted)
                }
            }
        case .restricted, .denied:
            // If permission is restricted or denied, inform the user
            completion(false)
        case .authorized:
            // Permission granted
            completion(true)
        default:
            completion(false)
        }
    }
    
    func setupCameraOnPermissionCheck() {
        requestCameraAccess { [weak self] permissionGranted in
            guard let wself = self else {return}
            if permissionGranted {
                DispatchQueue.global(qos: .background).async {
                    wself.setupCamera()
                }
            } else {
                wself.requiredPermissionAlert()
            }
        }
    }
    
    /// Setting up the camera -> This method effectively creates device instance from available video camera device, input is instantialised based on that device, output set to a metadata object

    func setupCamera() {
        
        guard session.inputs.isEmpty && session.outputs.isEmpty else {
                return  // Session is already set up, no need to reconfigure
        }
        
        //AVCaptureDevice represents the main or default camera device, here for video media type
        guard let device = AVCaptureDevice.default(for: .video) else { return }
        
        do {
            let input = try AVCaptureDeviceInput(device: device)
            
            let output = AVCaptureMetadataOutput()
            
            output.setMetadataObjectsDelegate(self, queue: DispatchQueue.main)
            
            session.addInput(input)
            session.addOutput(output)
            
            output.metadataObjectTypes = [.qr] //For QR code
            
            DispatchQueue.main.async {
                let previewLayer = AVCaptureVideoPreviewLayer(session: self.session) //It displays the camera preview in the view.
                previewLayer.videoGravity = .resizeAspectFill
                previewLayer.frame = CGRect(x: Int(self.qrCodeView.bounds.minX), y: Int(self.qrCodeView.bounds.minY), width: 300, height: 300)
                self.qrCodeView.layer.addSublayer(previewLayer)
                
            }
            session.startRunning()
            sessionStarted = true
        } catch {
            CommonUtility.showBasicAlert(on: self, title: "Scanning is not supported", message: "Your device does not support scanning a code from an item. Please use a device with a camera.")
            print(error)
        }
    }

    // MARK: Alerts
    
    func requiredPermissionAlert() {
        let alert = UIAlertController(title: "Camera Access Needed",
                                          message: "Please enable camera access in Settings to scan QR codes.",
                                          preferredStyle: .alert)

            alert.addAction(UIAlertAction(title: "Go to Settings", style: .default) { _ in
                guard let settingsUrl = URL(string: UIApplication.openSettingsURLString) else { return }
                if UIApplication.shared.canOpenURL(settingsUrl) {
                    UIApplication.shared.open(settingsUrl)
                }
            })

        alert.addAction(UIAlertAction(title: "Cancel", style: .cancel) { _ in
            self.navigationController?.popViewController(animated: true)
        })
            self.present(alert, animated: true)
    }

}

//MARK: Output Metadata Delegate

extension QRScannerViewController: AVCaptureMetadataOutputObjectsDelegate {
    
    ///This is called when the camera captures the metadata
    
    func metadataOutput(_ output: AVCaptureMetadataOutput, didOutput metadataObjects: [AVMetadataObject], from connection: AVCaptureConnection) {
        guard let metadataObject = metadataObjects.first as? AVMetadataMachineReadableCodeObject, metadataObject.type == .qr, let stringValue = metadataObject.stringValue else {
            return
        }
        session.stopRunning()
        let dict = CommonUtility.separateDeeplinkParamsIn(url: stringValue, byRemovingParams: nil)
        if let submitController = UIStoryboard(name: "Main", bundle: Bundle(for: SubmitPageViewController.self)).instantiateViewController(withIdentifier: String(describing: SubmitPageViewController.self)) as? SubmitPageViewController {
            let orderId = dict["tr"]
            let amount = dict["am"]
            let externalSerialNo = dict["tid"]
            let split = dict["split"]
            if let payeeVpa  = dict["pa"], !payeeVpa.isEmpty {
                submitController.updateLabels(amount: amount, orderId: orderId, payeeVpa: payeeVpa, externalSerialNo: externalSerialNo, split: split)
                submitController.modalPresentationStyle = .overCurrentContext
                navigationController?.pushViewController(submitController, animated: true)
            } else {
                CommonUtility.showBasicAlert(on: self, title: "QR Error", message: "QR code has no vpa parameter. Please use correct QR code!") {
                    self.navigationController?.popViewController(animated: true)
                }
            }
        }
    }
}
